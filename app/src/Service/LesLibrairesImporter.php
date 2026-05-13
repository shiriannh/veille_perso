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

class LesLibrairesImporter
{
    /**
     * @var array{window: ?string, pagesVisited: int, candidatesCount: int, detailsOpened: int}
     */
    private array $lastSummary = [
        'window' => null,
        'pagesVisited' => 0,
        'candidatesCount' => 0,
        'detailsOpened' => 0,
    ];

    public function __construct(
        private readonly LesLibrairesConnector $connector,
        private readonly GoogleBooksEnricher $googleBooksEnricher,
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryRepository $entryRepository,
        private readonly EntryAnalyzer $entryAnalyzer,
        private readonly EntryTagDetector $entryTagDetector,
        private readonly RssCategoryMapper $rssCategoryMapper,
    ) {
    }

    /**
     * @return array{window: string, pagesVisited: int, candidates: array<int, array<string, mixed>>, books: array<int, LesLibrairesBook>, errors: array<int, string>}
     */
    public function preview(Source $source, ?string $window = null, int $limit = 10): array
    {
        $this->assertImportable($source);
        $window = $this->resolveWindow($source, $window);
        $collection = $this->connector->collectCandidates($window, $source->getUrl());
        $candidates = array_slice($collection['candidates'], 0, max(1, $limit));
        $books = [];
        $errors = [];

        foreach ($candidates as $candidate) {
            try {
                $books[] = $this->connector->detail($candidate['url']);
            } catch (\Throwable $exception) {
                $errors[] = sprintf('%s : %s', $candidate['url'], $exception->getMessage());
            }
        }

        return [
            'window' => $window,
            'pagesVisited' => $collection['pagesVisited'],
            'candidates' => $candidates,
            'books' => $books,
            'errors' => $errors,
        ];
    }

    public function import(Source $source, ?string $window = null, bool $analyze = true, ?int $limit = null): ImportRun
    {
        $run = (new ImportRun())->setSource($source);
        $this->entityManager->persist($run);

        $now = new \DateTimeImmutable();
        $source->setLastFetchedAt($now);

        try {
            $this->assertImportable($source);
            $window = $this->resolveWindow($source, $window);
            $collection = $this->connector->collectCandidates($window, $source->getUrl());
            $candidates = $collection['candidates'];
            if ($limit !== null) {
                $candidates = array_slice($candidates, 0, max(1, $limit));
            }

            $created = 0;
            $skipped = 0;
            $detailsOpened = 0;
            $errors = [];

            foreach ($candidates as $candidate) {
                try {
                    ++$detailsOpened;
                    $book = $this->mergeGoogleBooks($this->connector->detail($candidate['url']));
                    $sourceHash = $this->sourceHash($book);
                    $duplicate = $this->entryRepository->findImportedDuplicate($source, $book->mainIdentifier(), $book->url, $sourceHash);
                    if ($duplicate instanceof Entry) {
                        ++$skipped;
                        continue;
                    }

                    $entry = $this->createEntry($source, $book, $now, $sourceHash);
                    $this->entryTagDetector->detect($entry);
                    $this->rssCategoryMapper->enrich($entry, $book->categories);

                    if ($analyze) {
                        $this->entryAnalyzer->analyze($entry);
                    }

                    $this->entityManager->persist($entry);
                    ++$created;
                } catch (\Throwable $exception) {
                    ++$skipped;
                    $errors[] = sprintf('%s : %s', $candidate['url'], $exception->getMessage());
                }
            }

            $run
                ->setStatus(ImportRunStatus::Success)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setFetchedCount(count($candidates))
                ->setCreatedCount($created)
                ->setSkippedCount($skipped)
                ->setErrorMessage($errors === [] ? null : implode("\n", array_slice($errors, 0, 20)));

            $source
                ->setLastSuccessAt($run->getFinishedAt())
                ->setLastErrorAt(null)
                ->setLastErrorMessage(null);

            $this->lastSummary = [
                'window' => $window,
                'pagesVisited' => $collection['pagesVisited'],
                'candidatesCount' => count($candidates),
                'detailsOpened' => $detailsOpened,
            ];
        } catch (\Throwable $exception) {
            $run
                ->setStatus(ImportRunStatus::Failed)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setErrorMessage($exception->getMessage());

            $source
                ->setLastErrorAt($run->getFinishedAt())
                ->setLastErrorMessage($exception->getMessage());

            $this->lastSummary = [
                'window' => $window,
                'pagesVisited' => 0,
                'candidatesCount' => 0,
                'detailsOpened' => 0,
            ];
        }

        $this->entityManager->flush();

        return $run;
    }

    /**
     * @return array{window: ?string, pagesVisited: int, candidatesCount: int, detailsOpened: int}
     */
    public function lastSummary(): array
    {
        return $this->lastSummary;
    }

    public function resolveWindow(Source $source, ?string $window = null): string
    {
        if (in_array($window, ['7d', '1m', '3m'], true)) {
            return $window;
        }

        $lastSuccessAt = $source->getLastSuccessAt();
        if (!$lastSuccessAt instanceof \DateTimeImmutable) {
            return '3m';
        }

        $ageInDays = max(0, (int) $lastSuccessAt->diff(new \DateTimeImmutable())->format('%a'));

        return match (true) {
            $ageInDays <= 7 => '7d',
            $ageInDays <= 31 => '1m',
            default => '3m',
        };
    }

    private function assertImportable(Source $source): void
    {
        if (!$source->isActive()) {
            throw new \RuntimeException('La source est inactive.');
        }

        if ($source->getFetchMode() !== FetchMode::LesLibrairesCatalog) {
            throw new \RuntimeException('La source n est pas configuree en connecteur leslibraires.fr.');
        }
    }

    private function mergeGoogleBooks(LesLibrairesBook $book): LesLibrairesBook
    {
        $google = $this->googleBooksEnricher->enrich($book->isbn ?? $book->ean13);
        if ($google === null) {
            return $book;
        }

        $book->description ??= is_string($google['description'] ?? null) ? $google['description'] : null;
        $book->pageCount ??= is_int($google['pageCount'] ?? null) ? $google['pageCount'] : null;
        $book->language ??= is_string($google['language'] ?? null) ? $google['language'] : null;
        $book->publisher ??= is_string($google['publisher'] ?? null) ? $google['publisher'] : null;
        $book->publishedAt ??= $this->parseGoogleDate(is_string($google['publishedDate'] ?? null) ? $google['publishedDate'] : null);
        if ($book->authors === [] && is_array($google['authors'] ?? null)) {
            $book->authors = array_values(array_filter(array_map('strval', $google['authors'])));
        }

        if (is_array($google['categories'] ?? null)) {
            $book->categories = array_values(array_unique(array_merge($book->categories, array_map('strval', $google['categories']))));
        }

        $book->raw['googleBooks'] = [
            'found' => true,
            'categories' => $google['categories'] ?? [],
            'imageLinks' => $google['imageLinks'] ?? [],
        ];

        return $book;
    }

    private function createEntry(Source $source, LesLibrairesBook $book, \DateTimeImmutable $now, string $sourceHash): Entry
    {
        $rawPayload = [
            'connector' => 'leslibraires',
            'isbn' => $book->isbn,
            'ean13' => $book->ean13,
            'authors' => $book->authors,
            'publisher' => $book->publisher,
            'format' => $book->format,
            'collection' => $book->collection,
            'pageCount' => $book->pageCount,
            'language' => $book->language,
            'categories' => $book->categories,
            'raw' => $book->raw,
        ];

        return (new Entry())
            ->setSource($source)
            ->setTitle($book->title)
            ->setMediaType(MediaType::Book)
            ->setMediaTypeOrigin('leslibraires')
            ->setDetectedMediaType(MediaType::Book)
            ->setMediaDetectionConfidence(90)
            ->setAuthorOrStudio($book->authors !== [] ? implode(', ', array_slice($book->authors, 0, 3)) : null)
            ->setOriginalUrl($book->url)
            ->setCanonicalUrl($book->url)
            ->setExternalId($book->mainIdentifier())
            ->setRawContent($book->description)
            ->setRawPayload($rawPayload)
            ->setSpottedAt($now)
            ->setPublishedAt($book->publishedAt)
            ->setImportedAt($now)
            ->setSourceHash($sourceHash)
            ->setInterestLevel(0)
            ->setStatus(EntryStatus::ToWatch)
            ->setPersonalTags(['leslibraires']);
    }

    private function parseGoogleDate(?string $value): ?\DateTimeImmutable
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

    private function sourceHash(LesLibrairesBook $book): string
    {
        return hash('sha256', mb_strtolower(implode('|', [
            $book->isbn ?? '',
            $book->ean13 ?? '',
            $book->url,
            $book->title,
            implode(',', $book->authors),
            $book->publishedAt?->format('Y-m-d') ?? '',
        ])));
    }
}
