<?php

namespace App\Service;

use App\Entity\InterestProfileRule;
use App\Repository\InterestProfileRuleRepository;

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
        private readonly ?InterestProfileRuleRepository $ruleRepository = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function positiveKeywords(): array
    {
        return array_values(array_unique(array_merge(
            $this->positiveKeywords,
            $this->ruleValues(['positive_keyword', 'preferred_media', 'license', 'studio', 'author']),
        )));
    }

    /**
     * @return array<int, string>
     */
    public function negativeKeywords(): array
    {
        return array_values(array_unique(array_merge($this->negativeKeywords, $this->ruleValues(['negative_keyword']))));
    }

    /**
     * @return array<int, string>
     */
    public function boostedPhrases(): array
    {
        return array_values(array_unique(array_merge($this->boostedPhrases, $this->ruleValues(['boosted_phrase']))));
    }

    /**
     * @return array<int, string>
     */
    public function excludedPhrases(): array
    {
        return array_values(array_unique(array_merge($this->excludedPhrases, $this->ruleValues(['excluded_phrase']))));
    }

    /**
     * @return array<string, int>
     */
    public function weightedPositiveTerms(): array
    {
        return $this->defaultWeights($this->positiveKeywords, 7) + $this->weightedRules(['positive_keyword', 'preferred_media', 'license', 'studio', 'author'], 7);
    }

    /**
     * @return array<string, int>
     */
    public function weightedBoostedPhrases(): array
    {
        return $this->defaultWeights($this->boostedPhrases, 7) + $this->weightedRules(['boosted_phrase'], 7);
    }

    /**
     * @return array<string, int>
     */
    public function weightedNegativeTerms(): array
    {
        return $this->defaultWeights($this->negativeKeywords, 18) + $this->weightedRules(['negative_keyword'], 18);
    }

    /**
     * @return array<string, int>
     */
    public function weightedExcludedPhrases(): array
    {
        return $this->defaultWeights($this->excludedPhrases, 18) + $this->weightedRules(['excluded_phrase'], 18);
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

    /**
     * @param array<int, string> $categories
     *
     * @return array<int, string>
     */
    private function ruleValues(array $categories): array
    {
        return array_keys($this->weightedRules($categories, 7));
    }

    /**
     * @param array<int, string> $categories
     *
     * @return array<string, int>
     */
    private function weightedRules(array $categories, int $fallbackWeight): array
    {
        if ($this->ruleRepository === null) {
            return [];
        }

        $rules = [];
        foreach ($this->ruleRepository->findActiveByCategories($categories) as $rule) {
            if (!$rule instanceof InterestProfileRule || $rule->getValue() === '') {
                continue;
            }

            $rules[$rule->getValue()] = $rule->scoreWeight();
        }

        return $rules === [] ? [] : array_map(static fn (int $weight): int => $weight ?: $fallbackWeight, $rules);
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<string, int>
     */
    private function defaultWeights(array $values, int $weight): array
    {
        $weighted = [];
        foreach ($values as $value) {
            $weighted[$value] = $weight;
        }

        return $weighted;
    }
}
