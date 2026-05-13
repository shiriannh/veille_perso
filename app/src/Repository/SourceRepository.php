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
        return $this->findFiltered();
    }

    /**
     * @return Source[]
     */
    public function findFiltered(bool $activeRssOnly = false, bool $lastImportError = false): array
    {
        $queryBuilder = $this->createQueryBuilder('source')
            ->orderBy('source.name', 'ASC');

        if ($activeRssOnly) {
            $queryBuilder
                ->andWhere('source.isActive = true')
                ->andWhere('source.fetchMode = :rssFetchMode')
                ->setParameter('rssFetchMode', FetchMode::Rss);
        }

        if ($lastImportError) {
            $queryBuilder->andWhere('source.lastErrorAt IS NOT NULL');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return Source[]
     */
    public function findActiveRssSources(): array
    {
        return $this->findActiveByFetchMode(FetchMode::Rss);
    }

    /**
     * @return Source[]
     */
    public function findActiveLesLibrairesSources(): array
    {
        return $this->findActiveByFetchMode(FetchMode::LesLibrairesCatalog);
    }

    /**
     * @return Source[]
     */
    public function findActiveByFetchMode(FetchMode $fetchMode): array
    {
        return $this->createQueryBuilder('source')
            ->andWhere('source.isActive = true')
            ->andWhere('source.fetchMode = :fetchMode')
            ->setParameter('fetchMode', $fetchMode)
            ->orderBy('source.importPriority', 'DESC')
            ->addOrderBy('source.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('source')
            ->select('COUNT(source.id)')
            ->andWhere('source.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Source[]
     */
    public function findWithLastImportError(int $limit = 5): array
    {
        return $this->createQueryBuilder('source')
            ->andWhere('source.lastErrorAt IS NOT NULL')
            ->orderBy('source.lastErrorAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
