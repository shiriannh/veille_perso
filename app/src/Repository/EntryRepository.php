<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entry>
 */
class EntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entry::class);
    }

    /**
     * @return Entry[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->orderBy('entry.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countWithoutReview(): int
    {
        return (int) $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->leftJoin('entry.review', 'review')
            ->andWhere('review.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findImportedDuplicate(Source $source, ?string $externalId, ?string $canonicalUrl, string $sourceHash): ?Entry
    {
        if ($externalId !== null && $externalId !== '') {
            $entry = $this->createQueryBuilder('entry')
                ->andWhere('entry.source = :source')
                ->andWhere('entry.externalId = :externalId')
                ->setParameter('source', $source)
                ->setParameter('externalId', $externalId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($entry !== null) {
                return $entry;
            }
        }

        if ($canonicalUrl !== null && $canonicalUrl !== '') {
            $entry = $this->createQueryBuilder('entry')
                ->andWhere('entry.source = :source')
                ->andWhere('entry.canonicalUrl = :canonicalUrl OR entry.originalUrl = :canonicalUrl')
                ->setParameter('source', $source)
                ->setParameter('canonicalUrl', $canonicalUrl)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($entry !== null) {
                return $entry;
            }
        }

        return $this->createQueryBuilder('entry')
            ->andWhere('entry.source = :source')
            ->andWhere('entry.sourceHash = :sourceHash')
            ->setParameter('source', $source)
            ->setParameter('sourceHash', $sourceHash)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
