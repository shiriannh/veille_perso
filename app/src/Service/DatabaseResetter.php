<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\ImportRun;
use App\Entity\Review;
use App\Entity\Source;
use App\Entity\SynthesisReport;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseResetter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{reports: int, reviews: int, entries: int, importRuns: int, sources: int}
     */
    public function reset(array $scopes = []): array
    {
        $scopes = $this->normalizeScopes($scopes);

        return $this->entityManager->wrapInTransaction(function () use ($scopes): array {
            $reports = in_array('reports', $scopes, true) ? $this->deleteAll(SynthesisReport::class) : 0;
            $reviews = in_array('reviews', $scopes, true) ? $this->deleteAll(Review::class) : 0;
            $entries = in_array('entries', $scopes, true) ? $this->deleteAll(Entry::class) : 0;
            $importRuns = in_array('importRuns', $scopes, true) ? $this->deleteAll(ImportRun::class) : 0;
            $sources = in_array('sources', $scopes, true) ? $this->deleteAll(Source::class) : 0;

            return [
                'reports' => $reports,
                'reviews' => $reviews,
                'entries' => $entries,
                'importRuns' => $importRuns,
                'sources' => $sources,
            ];
        });
    }

    /**
     * @param array<int, string> $scopes
     *
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $allowed = ['sources', 'entries', 'reviews', 'reports', 'importRuns'];
        $scopes = array_values(array_intersect($allowed, $scopes));

        if ($scopes === []) {
            return $allowed;
        }

        if (in_array('sources', $scopes, true)) {
            $scopes = array_merge($scopes, ['entries', 'importRuns']);
        }

        if (in_array('entries', $scopes, true)) {
            $scopes = array_merge($scopes, ['reviews', 'reports']);
        }

        if (in_array('reviews', $scopes, true)) {
            $scopes[] = 'reports';
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @param class-string $className
     */
    private function deleteAll(string $className): int
    {
        return $this->entityManager
            ->createQuery(sprintf('DELETE FROM %s entity', $className))
            ->execute();
    }
}
