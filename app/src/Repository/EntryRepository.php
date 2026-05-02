<?php

namespace App\Repository;

use App\Entity\Entry;
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
}
