<?php

namespace App\Repository;

use App\Entity\SynthesisReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SynthesisReport>
 */
class SynthesisReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SynthesisReport::class);
    }

    /**
     * @return SynthesisReport[]
     */
    public function findLatest(): array
    {
        return $this->createQueryBuilder('report')
            ->leftJoin('report.entries', 'entry')
            ->addSelect('entry')
            ->orderBy('report.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLastGenerated(): ?SynthesisReport
    {
        return $this->createQueryBuilder('report')
            ->orderBy('report.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
