<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\MediaType;
use App\Enum\ReviewNextAction;
use App\Enum\ReviewStatus;
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
     *     entryDecision?: ?AnalysisDecision,
     *     detectedTag?: ?string,
     *     status?: ?ReviewStatus,
     *     nextAction?: ?ReviewNextAction,
     *     autoCreatedDrafts?: ?bool,
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
                ->andWhere('entry.mediaType = :mediaType OR (entry.mediaType = :otherMediaType AND entry.detectedMediaType = :mediaType)')
                ->setParameter('mediaType', $filters['mediaType'])
                ->setParameter('otherMediaType', MediaType::Other);
        }

        if (($filters['entryDecision'] ?? null) instanceof AnalysisDecision) {
            $queryBuilder
                ->andWhere('entry.decision = :entryDecision')
                ->setParameter('entryDecision', $filters['entryDecision']);
        }

        if (($filters['status'] ?? null) instanceof ReviewStatus) {
            $queryBuilder
                ->andWhere('review.status = :reviewStatus')
                ->setParameter('reviewStatus', $filters['status']);
        }

        if (($filters['nextAction'] ?? null) instanceof ReviewNextAction) {
            $queryBuilder
                ->andWhere('review.nextAction = :nextAction')
                ->setParameter('nextAction', $filters['nextAction']);
        }

        if (($filters['autoCreatedDrafts'] ?? false) === true) {
            $queryBuilder
                ->andWhere('review.isAutoCreated = true')
                ->andWhere('review.isDraft = true');
        }

        match ($filters['sort'] ?? null) {
            'interest_desc' => $queryBuilder->orderBy('entry.interestLevel', 'DESC')->addOrderBy('entry.relevanceScore', 'DESC')->addOrderBy('review.updatedAt', 'DESC'),
            'score_desc' => $queryBuilder->orderBy('review.score', 'DESC')->addOrderBy('review.updatedAt', 'DESC'),
            'score_asc' => $queryBuilder->orderBy('review.score', 'ASC')->addOrderBy('review.updatedAt', 'DESC'),
            'updated_asc' => $queryBuilder->orderBy('review.updatedAt', 'ASC'),
            default => $queryBuilder->orderBy('review.updatedAt', 'DESC'),
        };

        $reviews = $queryBuilder->getQuery()->getResult();

        if (($filters['detectedTag'] ?? null) !== null && trim((string) $filters['detectedTag']) !== '') {
            $tag = mb_strtolower(trim((string) $filters['detectedTag']));

            return array_values(array_filter($reviews, static function (Review $review) use ($tag): bool {
                foreach ($review->getEntry()?->getDetectedTags() ?? [] as $detectedTag) {
                    if (mb_strtolower($detectedTag) === $tag) {
                        return true;
                    }
                }

                return false;
            }));
        }

        return $reviews;
    }
}
