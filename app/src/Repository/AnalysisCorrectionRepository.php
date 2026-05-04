<?php

namespace App\Repository;

use App\Entity\AnalysisCorrection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalysisCorrection>
 */
class AnalysisCorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalysisCorrection::class);
    }

    /**
     * @return array<int, AnalysisCorrection>
     */
    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('correction')
            ->leftJoin('correction.entry', 'entry')
            ->addSelect('entry')
            ->orderBy('correction.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
