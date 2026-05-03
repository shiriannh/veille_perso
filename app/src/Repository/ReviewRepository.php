<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Source;
use App\Enum\MediaType;
use App\Enum\ReviewVerdict;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findLatestUpdated(int $limit = 5): array
    {
        return $this->createQueryBuilder('review')
            ->leftJoin('review.entry', 'entry')
            ->addSelect('entry')
            ->orderBy('review.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{
     *     verdict?: ?ReviewVerdict,
     *     minScore?: ?int,
     *     source?: ?Source,
     *     mediaType?: ?MediaType,
     *     sort?: ?string
     * } $filters
     *
     * @return Review[]
     */
    public function findFiltered(array $filters): array
    {
        $queryBuilder = $this->createQueryBuilder('review')
            ->leftJoin('review.entry', 'entry')
            ->addSelect('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source');

        if (($filters['verdict'] ?? null) instanceof ReviewVerdict) {
            $queryBuilder
                ->andWhere('review.verdict = :verdict')
                ->setParameter('verdict', $filters['verdict']);
        }

        if (($filters['minScore'] ?? null) !== null) {
            $queryBuilder
                ->andWhere('review.score >= :minScore')
                ->setParameter('minScore', $filters['minScore']);
        }

        if (($filters['source'] ?? null) instanceof Source) {
            $queryBuilder
                ->andWhere('entry.source = :sourceFilter')
                ->setParameter('sourceFilter', $filters['source']);
        }

        if (($filters['mediaType'] ?? null) instanceof MediaType) {
            $queryBuilder
                ->andWhere('entry.mediaType = :mediaType')
                ->setParameter('mediaType', $filters['mediaType']);
        }

        match ($filters['sort'] ?? null) {
            'score_desc' => $queryBuilder->orderBy('review.score', 'DESC')->addOrderBy('review.updatedAt', 'DESC'),
            'score_asc' => $queryBuilder->orderBy('review.score', 'ASC')->addOrderBy('review.updatedAt', 'DESC'),
            'updated_asc' => $queryBuilder->orderBy('review.updatedAt', 'ASC'),
            default => $queryBuilder->orderBy('review.updatedAt', 'DESC'),
        };

        return $queryBuilder->getQuery()->getResult();
    }
}
