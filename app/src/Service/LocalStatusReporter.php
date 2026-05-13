<?php

namespace App\Service;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\Entry;
use App\Entity\FetchModeReference;
use App\Entity\ImportRun;
use App\Entity\MediaTypeReference;
use App\Entity\Source;
use App\Entity\SourceTypeReference;
use App\Entity\SynthesisReport;
use App\Enum\ImportRunStatus;
use App\Enum\MediaType;
use App\Repository\EntryRepository;
use App\Repository\ImportRunRepository;
use App\Repository\SourceRepository;
use App\Repository\SynthesisReportRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class LocalStatusReporter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly SourceRepository $sourceRepository,
        private readonly EntryRepository $entryRepository,
        private readonly ImportRunRepository $importRunRepository,
        private readonly SynthesisReportRepository $synthesisReportRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'appVersion' => $_ENV['APP_VERSION'] ?? $_SERVER['APP_VERSION'] ?? 'local',
            'databaseOk' => $this->databaseOk(),
            'latestMigration' => $this->latestMigration(),
            'missingReferences' => $this->missingReferences(),
            'sourceCount' => $this->count(Source::class),
            'activeSourceCount' => $this->sourceRepository->countActive(),
            'entryCount' => $this->count(Entry::class),
            'pendingAnalysisCount' => $this->entryRepository->countPendingAnalysisEntries(),
            'otherMediaCount' => $this->entryRepository->countStoredOtherMediaEntries(),
            'lastImportRun' => $this->importRunRepository->findLatest(1)[0] ?? null,
            'lastFailedImportRun' => $this->latestFailedImportRun(),
            'lastAnalysisAt' => $this->latestAnalysisAt(),
            'lastSynthesisReport' => $this->synthesisReportRepository->findLastGenerated(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'sources_total' => $this->count(Source::class),
            'sources_active' => $this->sourceRepository->countActive(),
            'entries_total' => $this->count(Entry::class),
            'entries_non_analyzed' => $this->entryRepository->countPendingAnalysisEntries(),
            'entries_other_media' => $this->entryRepository->countStoredOtherMediaEntries(),
            'entries_unsynthesized' => $this->entryRepository->countUnsynthesizedRelevantEntries(),
            'reviews_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM review'),
            'reviews_drafts' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM review WHERE is_draft = true'),
            'recent_import_errors' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM import_run WHERE status = 'failed' AND started_at >= NOW() - INTERVAL '30 days'"),
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public function mediaDistribution(): array
    {
        return $this->distribution('entry', 'media_type');
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public function decisionDistribution(): array
    {
        return $this->distribution('entry', 'decision');
    }

    private function databaseOk(): bool
    {
        try {
            return (int) $this->connection->fetchOne('SELECT 1') === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function latestMigration(): ?string
    {
        try {
            $version = $this->connection->fetchOne('SELECT version FROM doctrine_migration_versions ORDER BY executed_at DESC NULLS LAST, version DESC LIMIT 1');

            return is_string($version) ? $version : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function missingReferences(): array
    {
        $missing = [];
        $references = [
            MediaTypeReference::class => array_map(static fn (MediaType $media): string => $media->value, MediaType::cases()),
            SourceTypeReference::class => ['website', 'rss'],
            FetchModeReference::class => ['manual', 'rss'],
            DecisionTypeReference::class => ['relevant', 'maybe_relevant', 'ignored', 'clickbait'],
            ClickbaitLevelReference::class => ['clean', 'suspicious', 'clickbait'],
            AnalysisLanguageReference::class => ['fr', 'en', 'mixed', 'unknown'],
        ];

        foreach ($references as $className => $slugs) {
            $repository = $this->entityManager->getRepository($className);
            foreach ($slugs as $slug) {
                if ($repository->findOneBy(['slug' => $slug]) === null) {
                    $missing[] = substr(strrchr($className, '\\') ?: $className, 1).': '.$slug;
                }
            }
        }

        return $missing;
    }

    private function latestFailedImportRun(): ?ImportRun
    {
        return $this->entityManager->getRepository(ImportRun::class)
            ->createQueryBuilder('run')
            ->leftJoin('run.source', 'source')
            ->addSelect('source')
            ->andWhere('run.status = :status')
            ->setParameter('status', ImportRunStatus::Failed)
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function latestAnalysisAt(): ?\DateTimeImmutable
    {
        $value = $this->entityManager->getRepository(Entry::class)
            ->createQueryBuilder('entry')
            ->select('MAX(entry.analyzedAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return is_string($value) && $value !== '' ? new \DateTimeImmutable($value) : null;
    }

    /**
     * @param class-string $className
     */
    private function count(string $className): int
    {
        return (int) $this->entityManager->getRepository($className)
            ->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    private function distribution(string $table, string $column): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT COALESCE(%s, :empty) AS value, COUNT(*) AS count FROM %s GROUP BY %s ORDER BY count DESC, value ASC',
            $column,
            $table,
            $column,
        ), ['empty' => 'non_defini']);

        return array_map(static fn (array $row): array => [(string) $row['value'], (int) $row['count']], $rows);
    }
}
