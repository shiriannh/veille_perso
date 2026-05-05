<?php

namespace App\Service;

use App\Entity\Source;
use App\Enum\FetchMode;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RssFeedInspector
{
    public const MAX_FEED_BYTES = 2_500_000;
    public const MAX_PREVIEW_ITEMS = 20;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{
     *     ok: bool,
     *     title: ?string,
     *     lastItemAt: ?\DateTimeImmutable,
     *     itemCount: int,
     *     items: array<int, array{title: string, url: ?string, publishedAt: ?\DateTimeImmutable, categories: array<int, string>}>,
     *     errors: array<int, string>
     * }
     */
    public function inspect(Source $source, int $previewLimit = self::MAX_PREVIEW_ITEMS): array
    {
        $errors = $this->validateSource($source);
        if ($errors !== []) {
            return $this->result(errors: $errors);
        }

        $url = (string) $source->getFeedUrl();
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || gethostbynamel($host) === false) {
            return $this->result(errors: ['DNS introuvable pour le flux RSS.']);
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml'],
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return $this->result(errors: [sprintf('HTTP %d renvoye par le flux RSS.', $statusCode)]);
            }

            $content = $response->getContent(false);
            if (strlen($content) > self::MAX_FEED_BYTES) {
                return $this->result(errors: [sprintf('Flux trop volumineux : limite %d Mo.', (int) (self::MAX_FEED_BYTES / 1_000_000))]);
            }

            return $this->parse($content, $previewLimit);
        } catch (\Throwable $exception) {
            return $this->result(errors: ['Erreur de lecture du flux : '.$exception->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function validateSource(Source $source): array
    {
        $errors = [];
        if (!$source->isActive()) {
            $errors[] = 'La source est inactive.';
        }

        if ($source->getFetchMode() !== FetchMode::Rss) {
            $errors[] = 'La source n est pas configuree en import RSS.';
        }

        if ($source->getFeedUrl() === null || trim($source->getFeedUrl()) === '') {
            $errors[] = 'URL de flux RSS manquante.';
        } elseif (filter_var($source->getFeedUrl(), FILTER_VALIDATE_URL) === false) {
            $errors[] = 'URL de flux RSS invalide.';
        }

        return $errors;
    }

    /**
     * @return array{
     *     ok: bool,
     *     title: ?string,
     *     lastItemAt: ?\DateTimeImmutable,
     *     itemCount: int,
     *     items: array<int, array{title: string, url: ?string, publishedAt: ?\DateTimeImmutable, categories: array<int, string>}>,
     *     errors: array<int, string>
     * }
     */
    private function parse(string $content, int $previewLimit): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml instanceof \SimpleXMLElement) {
            $message = $xmlErrors !== [] ? trim($xmlErrors[0]->message) : 'XML illisible';

            return $this->result(errors: ['Flux XML invalide : '.$message]);
        }

        $rootName = strtolower($xml->getName());
        $feedTitle = null;
        $items = [];

        if ($rootName === 'rss' || $rootName === 'rdf') {
            $feedTitle = $this->clean((string) ($xml->channel->title ?? $xml->title ?? '')) ?: null;
            $nodes = $xml->channel->item ?? $xml->item;
            foreach ($nodes as $item) {
                $items[] = [
                    'title' => $this->clean((string) $item->title),
                    'url' => $this->clean((string) $item->link) ?: null,
                    'publishedAt' => $this->parseDate($this->clean((string) $item->pubDate) ?: null),
                    'categories' => $this->extractCategories($item),
                ];
            }
        } elseif ($rootName === 'feed') {
            $feedTitle = $this->clean((string) $xml->title) ?: null;
            foreach ($xml->entry as $entry) {
                $items[] = [
                    'title' => $this->clean((string) $entry->title),
                    'url' => $this->extractAtomLink($entry),
                    'publishedAt' => $this->parseDate($this->clean((string) ($entry->published ?? $entry->updated ?? '')) ?: null),
                    'categories' => $this->extractCategories($entry),
                ];
            }
        } else {
            return $this->result(errors: ['Format de flux non supporte.']);
        }

        if ($items === []) {
            return $this->result(title: $feedTitle, errors: ['Flux valide mais vide.']);
        }

        usort($items, static fn (array $a, array $b): int => ($b['publishedAt']?->getTimestamp() ?? 0) <=> ($a['publishedAt']?->getTimestamp() ?? 0));

        return $this->result(
            title: $feedTitle,
            lastItemAt: $items[0]['publishedAt'],
            itemCount: count($items),
            items: array_slice($items, 0, max(1, $previewLimit)),
        );
    }

    /**
     * @param array<int, array{title: string, url: ?string, publishedAt: ?\DateTimeImmutable, categories: array<int, string>}> $items
     * @param array<int, string> $errors
     *
     * @return array{ok: bool, title: ?string, lastItemAt: ?\DateTimeImmutable, itemCount: int, items: array<int, array{title: string, url: ?string, publishedAt: ?\DateTimeImmutable, categories: array<int, string>}>, errors: array<int, string>}
     */
    private function result(?string $title = null, ?\DateTimeImmutable $lastItemAt = null, int $itemCount = 0, array $items = [], array $errors = []): array
    {
        return [
            'ok' => $errors === [],
            'title' => $title,
            'lastItemAt' => $lastItemAt,
            'itemCount' => $itemCount,
            'items' => $items,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractCategories(\SimpleXMLElement $node): array
    {
        $categories = [];
        foreach (['category', 'categorie'] as $tagName) {
            foreach ($node->{$tagName} as $category) {
                $value = $this->clean((string) $category);
                if ($value !== '') {
                    $categories[] = $value;
                }
            }
        }

        return array_values(array_unique($categories));
    }

    private function extractAtomLink(\SimpleXMLElement $entry): ?string
    {
        foreach ($entry->link as $link) {
            $attributes = $link->attributes();
            $href = isset($attributes['href']) ? $this->clean((string) $attributes['href']) : '';
            if ($href !== '') {
                return $href;
            }
        }

        return null;
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

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))) ?? '');
    }
}
