<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\SynthesisReport;
use App\Enum\AnalysisDecision;
use App\Enum\MediaType;
use App\Repository\EntryRepository;
use App\Repository\SynthesisReportRepository;
use Doctrine\ORM\EntityManagerInterface;

class SynthesisReportGenerator
{
    private const COMMERCIAL_SIGNALS = [
        'acheter',
        'achat',
        'battle-pass',
        'battle pass',
        'bon-plan',
        'bon plan',
        'collector',
        'deal',
        'discount',
        'edition collector',
        'offre',
        'precommande',
        'pre-order',
        'preorder',
        'promo',
        'promotion',
        'soldes',
        'subscription',
    ];

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly SynthesisReportRepository $synthesisReportRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function generate(array $criteria): SynthesisReport
    {
        $now = new \DateTimeImmutable();
        $lastReport = $this->synthesisReportRepository->findLastGenerated();
        $criteria = $this->normalizeCriteria($criteria);
        $entries = $this->selectEntries($criteria);

        $report = (new SynthesisReport())
            ->setTitle($this->resolveTitle($criteria, $now))
            ->setNotes($this->optionalString($criteria['notes'] ?? null))
            ->setFromDate($this->criteriaDate($criteria['fromDate'] ?? null) ?? $lastReport?->getCreatedAt())
            ->setToDate($this->criteriaDate($criteria['toDate'] ?? null) ?? $now)
            ->setIncludeMaybeRelevant((bool) $criteria['includeMaybeRelevant'])
            ->setCriteria($criteria)
            ->setGeneratedContent($this->buildMarkdownContent($entries));

        $this->entityManager->wrapInTransaction(function () use ($report, $entries, $now): void {
            foreach ($entries as $entry) {
                $report->addEntry($entry);
                if ($entry->getReview() !== null) {
                    $report->addReview($entry->getReview());
                }
                $entry->setLastSynthesizedAt($now);
            }

            $this->entityManager->persist($report);
            $this->entityManager->flush();
        });

        return $report;
    }

    public function regenerate(SynthesisReport $sourceReport): SynthesisReport
    {
        $criteria = $sourceReport->getCriteria() ?? [
            'title' => 'Regeneration - '.$sourceReport->getTitle(),
            'notes' => $sourceReport->getNotes(),
            'includeMaybeRelevant' => $sourceReport->includeMaybeRelevant(),
        ];
        $criteria['title'] = 'Regeneration - '.$sourceReport->getTitle();

        return $this->generate($criteria);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return Entry[]
     */
    public function preview(array $criteria): array
    {
        return $this->selectEntries($this->normalizeCriteria($criteria));
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

    public function exportMarkdown(SynthesisReport $report): string
    {
        return $report->getGeneratedContent() ?: $this->buildMarkdownContent($report->getEntries());
    }

    public function exportStandaloneHtml(SynthesisReport $report): string
    {
        $body = [];
        $body[] = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>'.htmlspecialchars($report->getTitle()).'</title>';
        $body[] = '<style>body{font-family:system-ui,sans-serif;margin:40px;line-height:1.5;color:#202124}h1,h2{margin-bottom:.3em}.entry{border-top:1px solid #d9dfda;padding:14px 0}.muted{color:#62666d}.badge{display:inline-block;border-radius:999px;background:#eef3f0;padding:2px 8px;margin:2px;font-size:.85em}</style>';
        $body[] = '</head><body>';
        $body[] = '<h1>'.htmlspecialchars($report->getTitle()).'</h1>';
        $body[] = '<p class="muted">Generee le '.$report->getCreatedAt()->format('d/m/Y H:i').' - '.$report->getEntries()->count().' entree(s)</p>';

        foreach ($this->groupByMediaType($report->getEntries()) as $media => $entries) {
            $body[] = '<h2>'.htmlspecialchars($media).'</h2>';
            foreach ($entries as $entry) {
                $body[] = '<article class="entry">';
                $body[] = '<h3>'.htmlspecialchars($entry->getTitle()).'</h3>';
                $body[] = '<p class="muted">'.htmlspecialchars($entry->getSource()->getName()).' - '.$this->entryDate($entry).'</p>';
                if ($entry->getOriginalUrl()) {
                    $body[] = '<p><a href="'.htmlspecialchars($entry->getOriginalUrl()).'">'.htmlspecialchars($entry->getOriginalUrl()).'</a></p>';
                }
                if ($entry->getRawContent()) {
                    $body[] = '<p>'.htmlspecialchars($this->excerpt($entry)).'</p>';
                }
                $body[] = '<p>'.$this->htmlTags($entry).'</p>';
                $body[] = '<p>'.htmlspecialchars($entry->getDecisionReason() ?: 'Aucune raison enregistree').'</p>';
                $body[] = '</article>';
            }
        }

        $body[] = '</body></html>';

        return implode("\n", $body);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    private function normalizeCriteria(array $criteria): array
    {
        $mediaTypes = array_values(array_filter((array) ($criteria['mediaTypes'] ?? []), static fn (mixed $media): bool => is_string($media) && MediaType::tryFrom($media) instanceof MediaType));

        return [
            'title' => $this->optionalString($criteria['title'] ?? null),
            'notes' => $this->optionalString($criteria['notes'] ?? null),
            'fromDate' => $this->criteriaDate($criteria['fromDate'] ?? null)?->format(\DateTimeInterface::ATOM),
            'toDate' => $this->criteriaDate($criteria['toDate'] ?? null)?->format(\DateTimeInterface::ATOM),
            'mediaTypes' => $mediaTypes,
            'includeMaybeRelevant' => (bool) ($criteria['includeMaybeRelevant'] ?? false),
            'maybeMinimumScore' => $this->boundedInt($criteria['maybeMinimumScore'] ?? null, 0, 100),
            'minimumInterestLevel' => $this->boundedInt($criteria['minimumInterestLevel'] ?? null, 0, 5),
            'excludeCommercial' => (bool) ($criteria['excludeCommercial'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return Entry[]
     */
    private function selectEntries(array $criteria): array
    {
        $entries = $this->entryRepository->findEntriesForSynthesis((bool) $criteria['includeMaybeRelevant']);

        return array_values(array_filter($entries, function (Entry $entry) use ($criteria): bool {
            if (!$this->matchesDateWindow($entry, $criteria)) {
                return false;
            }

            if ($criteria['mediaTypes'] !== [] && !in_array($entry->getFinalMediaType()->value, $criteria['mediaTypes'], true)) {
                return false;
            }

            if ($criteria['minimumInterestLevel'] !== null && $entry->getInterestLevel() < $criteria['minimumInterestLevel']) {
                return false;
            }

            if ($entry->getDecision() === AnalysisDecision::MaybeRelevant && $criteria['maybeMinimumScore'] !== null && ($entry->getRelevanceScore() ?? 0) < $criteria['maybeMinimumScore']) {
                return false;
            }

            if ($criteria['excludeCommercial'] && $this->isCommercial($entry)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesDateWindow(Entry $entry, array $criteria): bool
    {
        $entryDate = $entry->getPublishedAt() ?? $entry->getImportedAt() ?? $entry->getCreatedAt();
        $fromDate = $this->criteriaDate($criteria['fromDate'] ?? null);
        $toDate = $this->criteriaDate($criteria['toDate'] ?? null);

        if ($fromDate !== null && $entryDate < $fromDate) {
            return false;
        }

        return !($toDate !== null && $entryDate > $toDate);
    }

    private function isCommercial(Entry $entry): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $entry->getTitle(),
            $entry->getRawContent(),
            implode(' ', $entry->getDetectedTags()),
            implode(' ', $entry->getAnalysisSignals()),
        ])));

        foreach (self::COMMERCIAL_SIGNALS as $signal) {
            if (str_contains($haystack, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param iterable<Entry> $entries
     */
    private function buildMarkdownContent(iterable $entries): string
    {
        $lines = [];

        foreach ($this->groupByMediaType($entries) as $mediaLabel => $mediaEntries) {
            $lines[] = '## '.$mediaLabel;
            foreach ($mediaEntries as $entry) {
                $lines[] = sprintf('- [%s](%s)', $entry->getTitle(), $entry->getOriginalUrl() ?: '#');
                $lines[] = sprintf('  - Source : %s', $entry->getSource()?->getName() ?? 'Source inconnue');
                $lines[] = sprintf('  - Date : %s', $this->entryDate($entry));
                $lines[] = sprintf('  - Score : %s/100, interet %d/5', $entry->getRelevanceScore() !== null ? (string) $entry->getRelevanceScore() : '-', $entry->getInterestLevel());
                if ($entry->getDetectedTags() !== []) {
                    $lines[] = sprintf('  - Tags : %s', implode(', ', array_slice($entry->getDetectedTags(), 0, 8)));
                }
                $lines[] = sprintf('  - Raison : %s', $entry->getDecisionReason() ?: 'Aucune raison enregistree');
                if ($entry->getRawContent()) {
                    $lines[] = sprintf('  - Extrait : %s', $this->excerpt($entry));
                }
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function resolveTitle(array $criteria, \DateTimeImmutable $now): string
    {
        $title = $this->optionalString($criteria['title'] ?? null);

        return $title ?? 'Synthese du '.$now->format('d/m/Y H:i');
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function criteriaDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function boundedInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    private function entryDate(Entry $entry): string
    {
        return ($entry->getPublishedAt() ?? $entry->getImportedAt() ?? $entry->getCreatedAt())->format('d/m/Y');
    }

    private function excerpt(Entry $entry): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $entry->getRawContent())) ?? '');

        return mb_strlen($text) > 260 ? mb_substr($text, 0, 260).'...' : $text;
    }

    private function htmlTags(Entry $entry): string
    {
        return implode('', array_map(static fn (string $tag): string => '<span class="badge">'.htmlspecialchars($tag).'</span>', array_slice($entry->getDetectedTags(), 0, 8)));
    }
}
