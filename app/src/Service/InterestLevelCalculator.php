<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\ClickbaitLevel;
use App\Enum\MediaType;

class InterestLevelCalculator
{
    public function calculate(Entry $entry): int
    {
        $score = (int) round(((int) ($entry->getRelevanceScore() ?? 0)) * 0.70);

        $score += match ($entry->getDecision()) {
            AnalysisDecision::Relevant => 18,
            AnalysisDecision::MaybeRelevant => 6,
            AnalysisDecision::Clickbait => -35,
            AnalysisDecision::Ignored => -18,
            null => 0,
        };

        $score += min(12, count($entry->getMatchedPositiveKeywords()) * 2);
        $score -= min(20, count($entry->getMatchedNegativeKeywords()) * 5);

        if ($entry->getFinalMediaType() !== MediaType::Other) {
            $score += 5;
        }

        if ($entry->getDetectedMediaType() !== null && $entry->getDetectedMediaType() === $entry->getFinalMediaType()) {
            $score += 3;
        }

        if ($entry->getClickbaitLevel() === ClickbaitLevel::Suspicious) {
            $score -= 8;
        }

        if ($entry->getClickbaitLevel() === ClickbaitLevel::Clickbait) {
            $score -= 30;
        }

        return match (true) {
            $score >= 95 => 5,
            $score >= 82 => 4,
            $score >= 68 => 3,
            $score >= 50 => 2,
            $score >= 30 => 1,
            default => 0,
        };
    }
}
