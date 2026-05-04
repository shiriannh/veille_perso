<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
use App\Enum\EntryStatus;
use App\Enum\MediaType;
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

    /**
     * @param array{
     *     q?: ?string,
     *     source?: ?Source,
     *     mediaType?: ?MediaType,
     *     detectedMediaType?: ?MediaType,
     *     mediaTypeOrigin?: ?string,
     *     status?: ?EntryStatus,
     *     interestLevel?: ?int,
     *     reviewState?: ?string,
     *     decision?: ?AnalysisDecision,
     *     clickbaitLevel?: ?ClickbaitLevel,
     *     keyword?: ?string,
     *     detectedTag?: ?string,
     *     analysisLanguage?: ?string,
     *     sort?: ?string
     * } $filters
     *
     * @return Entry[]
     */
    public function findFiltered(array $filters): array
    {
        $queryBuilder = $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->leftJoin('entry.review', 'review')
            ->addSelect('review');

        if (($filters['q'] ?? null) !== null && trim((string) $filters['q']) !== '') {
            $queryBuilder
                ->andWhere('LOWER(entry.title) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim((string) $filters['q'])).'%');
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

        if (($filters['detectedMediaType'] ?? null) instanceof MediaType) {
            $queryBuilder
                ->andWhere('entry.detectedMediaType = :detectedMediaType')
                ->setParameter('detectedMediaType', $filters['detectedMediaType']);
        }

        if (($filters['mediaTypeOrigin'] ?? null) !== null && trim((string) $filters['mediaTypeOrigin']) !== '') {
            $origin = trim((string) $filters['mediaTypeOrigin']);
            if ($origin === 'unknown') {
                $queryBuilder->andWhere('entry.mediaTypeOrigin IS NULL OR entry.mediaTypeOrigin = :mediaTypeOrigin');
            } else {
                $queryBuilder->andWhere('entry.mediaTypeOrigin = :mediaTypeOrigin');
            }

            $queryBuilder->setParameter('mediaTypeOrigin', $origin);
        }

        if (($filters['status'] ?? null) instanceof EntryStatus) {
            $queryBuilder
                ->andWhere('entry.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (($filters['interestLevel'] ?? null) !== null) {
            $queryBuilder
                ->andWhere('entry.interestLevel = :interestLevel')
                ->setParameter('interestLevel', $filters['interestLevel']);
        }

        if (($filters['reviewState'] ?? null) === 'with') {
            $queryBuilder->andWhere('review.id IS NOT NULL');
        }

        if (($filters['reviewState'] ?? null) === 'without') {
            $queryBuilder->andWhere('review.id IS NULL');
        }

        if (($filters['decision'] ?? null) instanceof AnalysisDecision) {
            $queryBuilder
                ->andWhere('entry.decision = :decision')
                ->setParameter('decision', $filters['decision']);
        }

        if (($filters['clickbaitLevel'] ?? null) instanceof ClickbaitLevel) {
            $queryBuilder
                ->andWhere('entry.clickbaitLevel = :clickbaitLevel')
                ->setParameter('clickbaitLevel', $filters['clickbaitLevel']);
        }

        if (($filters['keyword'] ?? null) !== null && trim((string) $filters['keyword']) !== '') {
            $queryBuilder
                ->andWhere('LOWER(entry.normalizedTitle) LIKE :analysisKeyword OR LOWER(entry.normalizedContent) LIKE :analysisKeyword')
                ->setParameter('analysisKeyword', '%'.mb_strtolower(trim((string) $filters['keyword'])).'%');
        }

        if (($filters['analysisLanguage'] ?? null) !== null && trim((string) $filters['analysisLanguage']) !== '') {
            $queryBuilder
                ->andWhere('entry.analysisLanguage = :analysisLanguage')
                ->setParameter('analysisLanguage', trim((string) $filters['analysisLanguage']));
        }

        match ($filters['sort'] ?? null) {
            'published_asc' => $queryBuilder->orderBy('entry.publishedAt', 'ASC')->addOrderBy('entry.createdAt', 'DESC'),
            'published_desc' => $queryBuilder->orderBy('entry.publishedAt', 'DESC')->addOrderBy('entry.createdAt', 'DESC'),
            'imported_asc' => $queryBuilder->orderBy('entry.importedAt', 'ASC')->addOrderBy('entry.createdAt', 'DESC'),
            'imported_desc' => $queryBuilder->orderBy('entry.importedAt', 'DESC')->addOrderBy('entry.createdAt', 'DESC'),
            default => $queryBuilder->orderBy('entry.createdAt', 'DESC'),
        };

        $entries = $queryBuilder->getQuery()->getResult();

        if (($filters['detectedTag'] ?? null) !== null && trim((string) $filters['detectedTag']) !== '') {
            $tag = mb_strtolower(trim((string) $filters['detectedTag']));

            return array_values(array_filter($entries, static function (Entry $entry) use ($tag): bool {
                foreach ($entry->getDetectedTags() as $detectedTag) {
                    if (mb_strtolower($detectedTag) === $tag) {
                        return true;
                    }
                }

                return false;
            }));
        }

        return $entries;
    }

    /**
     * @return Entry[]
     */
    public function findPendingAnalysis(int $limit = 100): array
    {
        return $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->andWhere('entry.analysisStatus = :pending OR entry.decision IS NULL')
            ->setParameter('pending', AnalysisStatus::Pending)
            ->orderBy('entry.importedAt', 'DESC')
            ->addOrderBy('entry.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Entry[]
     */
    public function findWithoutDetectedTags(int $limit = 100): array
    {
        $entries = $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->orderBy('entry.importedAt', 'DESC')
            ->addOrderBy('entry.createdAt', 'DESC')
            ->setMaxResults(max($limit * 3, $limit))
            ->getQuery()
            ->getResult();

        return array_slice(array_values(array_filter($entries, static fn (Entry $entry): bool => $entry->getDetectedTags() === [])), 0, $limit);
    }

    /**
     * @return Entry[]
     */
    public function findLatestImported(int $limit = 5): array
    {
        return $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->andWhere('entry.importedAt IS NOT NULL')
            ->orderBy('entry.importedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Entry[]
     */
    public function findWithoutReview(int $limit = 5): array
    {
        return $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->leftJoin('entry.review', 'review')
            ->andWhere('review.id IS NULL')
            ->orderBy('entry.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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

    /**
     * @return Entry[]
     */
    public function findEntriesForSynthesis(bool $includeMaybeRelevant): array
    {
        $decisions = [AnalysisDecision::Relevant];
        if ($includeMaybeRelevant) {
            $decisions[] = AnalysisDecision::MaybeRelevant;
        }

        return $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->andWhere('entry.decision IN (:decisions)')
            ->andWhere('entry.lastSynthesizedAt IS NULL')
            ->setParameter('decisions', $decisions)
            ->orderBy('entry.mediaType', 'ASC')
            ->addOrderBy('entry.detectedMediaType', 'ASC')
            ->addOrderBy('entry.publishedAt', 'DESC')
            ->addOrderBy('entry.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Entry[]
     */
    public function findStoredOtherMediaEntries(?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('entry')
            ->leftJoin('entry.source', 'source')
            ->addSelect('source')
            ->andWhere('entry.mediaType = :other')
            ->setParameter('other', MediaType::Other)
            ->orderBy('entry.relevanceScore', 'DESC')
            ->addOrderBy('entry.importedAt', 'DESC')
            ->addOrderBy('entry.createdAt', 'DESC');

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function summarizeMediaTypes(): array
    {
        $summary = [];

        foreach ($this->createQueryBuilder('entry')->getQuery()->getResult() as $entry) {
            \assert($entry instanceof Entry);
            $media = $entry->getMediaType()->value;
            $origin = $entry->getMediaTypeOrigin() ?: 'unknown';

            $summary[$media] ??= [];
            $summary[$media][$origin] = ($summary[$media][$origin] ?? 0) + 1;
        }

        ksort($summary);

        return $summary;
    }
}
