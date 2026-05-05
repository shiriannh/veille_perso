<?php

namespace App\Service;

use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Repository\SourceRepository;

class SourceQualityReporter
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
    ) {
    }

    /**
     * @return array<int, array{label: string, class: string, score: int, total: int}>
     */
    public function summarizeAll(): array
    {
        $summary = [];

        foreach ($this->sourceRepository->findAllOrdered() as $source) {
            if ($source->getId() !== null) {
                $summary[$source->getId()] = $this->summarize($source);
            }
        }

        return $summary;
    }

    /**
     * @return array{label: string, class: string, score: int, total: int}
     */
    public function summarize(Source $source): array
    {
        $score = 0;
        $total = 0;

        foreach ($source->getEntries() as $entry) {
            $decision = $entry->getDecision();
            if (!$decision instanceof AnalysisDecision) {
                continue;
            }

            ++$total;
            $score += match ($decision) {
                AnalysisDecision::Relevant => 2,
                AnalysisDecision::MaybeRelevant => 1,
                AnalysisDecision::Ignored => -1,
                AnalysisDecision::Clickbait => -2,
            };
        }

        if ($total < 3) {
            return ['label' => 'source neuve', 'class' => 'badge-muted', 'score' => $score, 'total' => $total];
        }

        $ratio = $score / max(1, $total);

        if ($ratio >= 0.9) {
            return ['label' => 'source fiable', 'class' => 'badge-decision-relevant', 'score' => $score, 'total' => $total];
        }

        if ($ratio <= -0.35) {
            return ['label' => 'source bruyante', 'class' => 'badge-clickbait-suspicious', 'score' => $score, 'total' => $total];
        }

        return ['label' => 'source mixte', 'class' => 'badge-muted', 'score' => $score, 'total' => $total];
    }
}
