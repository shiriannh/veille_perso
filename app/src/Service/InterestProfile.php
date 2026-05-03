<?php

namespace App\Service;

class InterestProfile
{
    /**
     * @param array<int, string> $positiveKeywords
     * @param array<int, string> $negativeKeywords
     * @param array<int, string> $boostedPhrases
     * @param array<int, string> $excludedPhrases
     */
    public function __construct(
        private readonly array $positiveKeywords,
        private readonly array $negativeKeywords,
        private readonly array $boostedPhrases,
        private readonly array $excludedPhrases,
        private readonly int $minimumRelevanceScore,
        private readonly int $clickbaitSuspicionThreshold,
        private readonly int $clickbaitThreshold,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function positiveKeywords(): array
    {
        return $this->positiveKeywords;
    }

    /**
     * @return array<int, string>
     */
    public function negativeKeywords(): array
    {
        return $this->negativeKeywords;
    }

    /**
     * @return array<int, string>
     */
    public function boostedPhrases(): array
    {
        return $this->boostedPhrases;
    }

    /**
     * @return array<int, string>
     */
    public function excludedPhrases(): array
    {
        return $this->excludedPhrases;
    }

    public function minimumRelevanceScore(): int
    {
        return $this->minimumRelevanceScore;
    }

    public function clickbaitSuspicionThreshold(): int
    {
        return $this->clickbaitSuspicionThreshold;
    }

    public function clickbaitThreshold(): int
    {
        return $this->clickbaitThreshold;
    }
}
