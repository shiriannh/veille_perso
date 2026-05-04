<?php

namespace App\Repository;

use App\Entity\InterestProfileRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InterestProfileRule>
 */
class InterestProfileRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterestProfileRule::class);
    }

    /**
     * @return InterestProfileRule[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('rule')
            ->orderBy('rule.category', 'ASC')
            ->addOrderBy('rule.value', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, string> $categories
     *
     * @return InterestProfileRule[]
     */
    public function findActiveByCategories(array $categories): array
    {
        if ($categories === []) {
            return [];
        }

        return $this->createQueryBuilder('rule')
            ->andWhere('rule.isActive = true')
            ->andWhere('rule.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->orderBy('rule.weight', 'DESC')
            ->addOrderBy('rule.value', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
