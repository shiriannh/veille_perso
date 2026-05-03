<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\ImportRun;
use App\Entity\Review;
use App\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseResetter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{reviews: int, entries: int, importRuns: int, sources: int}
     */
    public function reset(): array
    {
        return $this->entityManager->wrapInTransaction(function (): array {
            $reviews = $this->deleteAll(Review::class);
            $entries = $this->deleteAll(Entry::class);
            $importRuns = $this->deleteAll(ImportRun::class);
            $sources = $this->deleteAll(Source::class);

            return [
                'reviews' => $reviews,
                'entries' => $entries,
                'importRuns' => $importRuns,
                'sources' => $sources,
            ];
        });
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
