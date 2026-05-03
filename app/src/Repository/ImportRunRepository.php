<?php

namespace App\Repository;

use App\Entity\ImportRun;
use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportRun>
 */
class ImportRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportRun::class);
    }

    /**
     * @return ImportRun[]
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('run')
            ->leftJoin('run.source', 'source')
            ->addSelect('source')
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ImportRun[]
     */
    public function findLatestFiltered(?Source $source = null, int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder('run')
            ->leftJoin('run.source', 'source')
            ->addSelect('source')
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults($limit);

        if ($source !== null) {
            $queryBuilder
                ->andWhere('run.source = :source')
                ->setParameter('source', $source);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return ImportRun[]
     */
    public function findLatestForSource(Source $source, int $limit = 20): array
    {
        return $this->createQueryBuilder('run')
            ->andWhere('run.source = :source')
            ->setParameter('source', $source)
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
