<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\ImportRun;
use App\Entity\Source;
use App\Enum\EntryStatus;
use App\Enum\FetchMode;
use App\Enum\ImportRunStatus;
use App\Enum\MediaType;
use App\Repository\EntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RssImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryRepository $entryRepository,
        private readonly EntryAnalyzer $entryAnalyzer,
        private readonly EntryTagDetector $entryTagDetector,
    ) {
    }

    public function import(Source $source): ImportRun
    {
        $run = (new ImportRun())->setSource($source);
        $this->entityManager->persist($run);

        $now = new \DateTimeImmutable();
        $source->setLastFetchedAt($now);

        try {
            $this->assertImportable($source);

            $response = $this->httpClient->request('GET', (string) $source->getFeedUrl(), [
                'headers' => ['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml'],
                'timeout' => 20,
            ]);
            $content = $response->getContent();
            $items = $this->parseFeed($content);

            $createdCount = 0;
            $skippedCount = 0;

            foreach ($items as $item) {
                if ($item['title'] === '') {
                    ++$skippedCount;
                    continue;
                }

                $sourceHash = $this->buildSourceHash($item);
                $duplicate = $this->entryRepository->findImportedDuplicate(
                    $source,
                    $item['externalId'],
                    $item['canonicalUrl'],
                    $sourceHash,
                );

                if ($duplicate !== null) {
                    ++$skippedCount;
                    continue;
                }

                $entry = (new Entry())
                    ->setSource($source)
                    ->setTitle($item['title'])
                    ->setMediaType(MediaType::Other)
                    ->setOriginalUrl($item['canonicalUrl'])
                    ->setCanonicalUrl($item['canonicalUrl'])
                    ->setExternalId($item['externalId'])
                    ->setRawContent($item['summary'])
                    ->setRawPayload($item['rawPayload'])
                    ->setSpottedAt($now)
                    ->setPublishedAt($item['publishedAt'])
                    ->setImportedAt($now)
                    ->setSourceHash($sourceHash)
                    ->setInterestLevel(0)
                    ->setStatus(EntryStatus::ToWatch)
                    ->setPersonalTags(['rss']);

                $this->entryTagDetector->detect($entry);
                $this->entryAnalyzer->analyze($entry);
                $this->entityManager->persist($entry);
                ++$createdCount;
            }

            $run
                ->setStatus(ImportRunStatus::Success)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setFetchedCount(count($items))
                ->setCreatedCount($createdCount)
                ->setSkippedCount($skippedCount);

            $source
                ->setLastSuccessAt($run->getFinishedAt())
                ->setLastErrorAt(null)
                ->setLastErrorMessage(null);
        } catch (\Throwable $exception) {
            $run
                ->setStatus(ImportRunStatus::Failed)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setErrorMessage($exception->getMessage());

            $source
                ->setLastErrorAt($run->getFinishedAt())
                ->setLastErrorMessage($exception->getMessage());
        }

        $this->entityManager->flush();

        return $run;
    }

    private function assertImportable(Source $source): void
    {
        if (!$source->isActive()) {
            throw new \RuntimeException('La source est inactive.');
        }

        if ($source->getFetchMode() !== FetchMode::Rss) {
            throw new \RuntimeException('La source n’est pas configurée en import RSS.');
        }

        if ($source->getFeedUrl() === null || trim($source->getFeedUrl()) === '') {
            throw new \RuntimeException('La source n’a pas d’URL de flux RSS.');
        }
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     externalId: ?string,
     *     canonicalUrl: ?string,
     *     summary: ?string,
     *     publishedAt: ?\DateTimeImmutable,
     *     rawPayload: array<string, ?string>
     * }>
     */
    private function parseFeed(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Flux XML invalide.');
        }

        $rootName = strtolower($xml->getName());

        if ($rootName === 'rss' || $rootName === 'rdf') {
            return $this->parseRssItems($xml);
        }

        if ($rootName === 'feed') {
            return $this->parseAtomEntries($xml);
        }

        throw new \RuntimeException('Format de flux non supporté.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseRssItems(\SimpleXMLElement $xml): array
    {
        $items = [];
        $nodes = $xml->channel->item ?? $xml->item;

        foreach ($nodes as $item) {
            $title = $this->clean((string) $item->title);
            $url = $this->clean((string) $item->link) ?: null;
            $guid = $this->clean((string) $item->guid) ?: null;
            $publishedAt = $this->parseDate($this->clean((string) $item->pubDate) ?: null);
            $summary = $this->clean((string) ($item->description ?? '')) ?: null;

            $items[] = [
                'title' => $title,
                'externalId' => $guid,
                'canonicalUrl' => $url,
                'summary' => $summary,
                'publishedAt' => $publishedAt,
                'rawPayload' => [
                    'title' => $title,
                    'guid' => $guid,
                    'link' => $url,
                    'publishedAt' => $publishedAt?->format(\DateTimeInterface::ATOM),
                    'summary' => $summary,
                ],
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseAtomEntries(\SimpleXMLElement $xml): array
    {
        $items = [];

        foreach ($xml->entry as $entry) {
            $title = $this->clean((string) $entry->title);
            $url = $this->extractAtomLink($entry);
            $externalId = $this->clean((string) $entry->id) ?: null;
            $publishedAt = $this->parseDate(
                $this->clean((string) ($entry->published ?? ''))
                ?: $this->clean((string) ($entry->updated ?? ''))
                ?: null,
            );
            $summary = $this->clean((string) ($entry->summary ?? $entry->content ?? '')) ?: null;

            $items[] = [
                'title' => $title,
                'externalId' => $externalId,
                'canonicalUrl' => $url,
                'summary' => $summary,
                'publishedAt' => $publishedAt,
                'rawPayload' => [
                    'title' => $title,
                    'id' => $externalId,
                    'link' => $url,
                    'publishedAt' => $publishedAt?->format(\DateTimeInterface::ATOM),
                    'summary' => $summary,
                ],
            ];
        }

        return $items;
    }

    private function extractAtomLink(\SimpleXMLElement $entry): ?string
    {
        $fallback = null;

        foreach ($entry->link as $link) {
            $attributes = $link->attributes();
            $href = isset($attributes['href']) ? $this->clean((string) $attributes['href']) : '';
            if ($href === '') {
                continue;
            }

            $rel = isset($attributes['rel']) ? (string) $attributes['rel'] : 'alternate';
            if ($rel === 'alternate') {
                return $href;
            }

            $fallback ??= $href;
        }

        return $fallback;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{title: string, externalId: ?string, canonicalUrl: ?string, publishedAt: ?\DateTimeImmutable} $item
     */
    private function buildSourceHash(array $item): string
    {
        return hash('sha256', strtolower(implode('|', [
            $item['title'],
            $item['canonicalUrl'] ?? '',
            $item['publishedAt']?->format('Y-m-d') ?? '',
        ])));
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))) ?? '');
    }
}
