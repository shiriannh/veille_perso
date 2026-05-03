<?php

namespace App\Repository;

use App\Entity\Source;
use App\Enum\FetchMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * @return Source[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('source')
            ->orderBy('source.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Source[]
     */
    public function findActiveRssSources(): array
    {
        return $this->createQueryBuilder('source')
            ->andWhere('source.isActive = true')
            ->andWhere('source.fetchMode = :fetchMode')
            ->setParameter('fetchMode', FetchMode::Rss)
            ->orderBy('source.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
