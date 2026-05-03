<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\SynthesisReport;
use App\Repository\EntryRepository;
use App\Repository\SynthesisReportRepository;
use Doctrine\ORM\EntityManagerInterface;

class SynthesisReportGenerator
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly SynthesisReportRepository $synthesisReportRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function generate(?string $title, ?string $notes, bool $includeMaybeRelevant): SynthesisReport
    {
        $now = new \DateTimeImmutable();
        $lastReport = $this->synthesisReportRepository->findLastGenerated();
        $entries = $this->entryRepository->findEntriesForSynthesis($includeMaybeRelevant);

        $report = (new SynthesisReport())
            ->setTitle($title !== null && trim($title) !== '' ? trim($title) : 'Synthèse du '.$now->format('d/m/Y H:i'))
            ->setNotes($notes !== null && trim($notes) !== '' ? trim($notes) : null)
            ->setFromDate($lastReport?->getCreatedAt())
            ->setToDate($now)
            ->setIncludeMaybeRelevant($includeMaybeRelevant)
            ->setGeneratedContent($this->buildGeneratedContent($entries));

        $this->entityManager->wrapInTransaction(function () use ($report, $entries, $now): void {
            foreach ($entries as $entry) {
                $report->addEntry($entry);
                $entry->setLastSynthesizedAt($now);
            }

            $this->entityManager->persist($report);
            $this->entityManager->flush();
        });

        return $report;
    }

    /**
     * @param iterable<Entry> $entries
     *
     * @return array<string, array<int, Entry>>
     */
    public function groupByMediaType(iterable $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $mediaType = $entry->getFinalMediaType();
            $label = $mediaType->label();
            $groups[$label] ??= [];
            $groups[$label][] = $entry;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param iterable<Entry> $entries
     */
    private function buildGeneratedContent(iterable $entries): string
    {
        $lines = [];

        foreach ($this->groupByMediaType($entries) as $mediaLabel => $mediaEntries) {
            $lines[] = '## '.$mediaLabel;
            foreach ($mediaEntries as $entry) {
                $lines[] = sprintf(
                    '- %s (%s, score %s)',
                    $entry->getTitle(),
                    $entry->getSource()?->getName() ?? 'Source inconnue',
                    $entry->getRelevanceScore() !== null ? (string) $entry->getRelevanceScore() : '-',
                );
            }
        }

        return implode("\n", $lines);
    }
}
