<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;

class EntryAnalyzer
{
    public const VERSION = 'rules-v1';

    /**
     * @var array<int, string>
     */
    private const CLICKBAIT_PHRASES = [
        'vous n allez pas croire',
        'incroyable',
        'le choc',
        'secret',
        'la verite sur',
        'personne n etait pret',
        'tout le monde en parle',
        '10 raisons',
        '5 raisons',
        'ce que personne ne vous dit',
        'enfin',
        'revelation',
        'scandale',
    ];

    public function __construct(
        private readonly InterestProfile $profile,
        private readonly OptionalAiEntryAnalyzer $aiAnalyzer,
    ) {
    }

    public function analyze(Entry $entry, bool $forceAi = false): void
    {
        $normalizedTitle = $this->normalize($entry->getTitle());
        $normalizedContent = $this->normalize((string) $entry->getRawContent());
        $haystack = trim($normalizedTitle.' '.$normalizedContent);

        $positiveMatches = $this->matchNeedles($haystack, $this->profile->positiveKeywords());
        $negativeMatches = $this->matchNeedles($haystack, $this->profile->negativeKeywords());
        $boostedMatches = $this->matchNeedles($haystack, $this->profile->boostedPhrases());
        $excludedMatches = $this->matchNeedles($haystack, $this->profile->excludedPhrases());

        $relevanceScore = $this->scoreRelevance($positiveMatches, $negativeMatches, $boostedMatches, $excludedMatches);
        [$clickbaitScore, $clickbaitSignals] = $this->scoreClickbait($entry->getTitle(), $normalizedTitle);
        $clickbaitLevel = $this->clickbaitLevel($clickbaitScore);

        $aiResult = null;
        if ($forceAi || $this->shouldAskAi($relevanceScore, $clickbaitScore)) {
            $aiResult = $this->aiAnalyzer->analyze($entry, [
                'relevanceScore' => $relevanceScore,
                'clickbaitScore' => $clickbaitScore,
                'positiveKeywords' => $positiveMatches,
                'negativeKeywords' => $negativeMatches,
                'clickbaitSignals' => $clickbaitSignals,
            ]);
        }

        $decision = $this->decide($relevanceScore, $clickbaitLevel, $aiResult);
        $reason = $this->buildReason($decision, $positiveMatches, $negativeMatches, $clickbaitSignals, $aiResult);

        $entry
            ->setNormalizedTitle($normalizedTitle)
            ->setNormalizedContent($normalizedContent !== '' ? $normalizedContent : null)
            ->setRelevanceScore($relevanceScore)
            ->setClickbaitScore($clickbaitScore)
            ->setClickbaitLevel($clickbaitLevel)
            ->setDecision($decision)
            ->setDecisionReason($reason)
            ->setMatchedPositiveKeywords(array_merge($positiveMatches, $boostedMatches))
            ->setMatchedNegativeKeywords(array_merge($negativeMatches, $excludedMatches))
            ->setClickbaitSignals($clickbaitSignals)
            ->setAnalysisVersion(self::VERSION)
            ->setAnalyzedAt(new \DateTimeImmutable());

        if ($aiResult !== null) {
            $entry
                ->setAiAnalyzedAt(new \DateTimeImmutable())
                ->setAiModel(is_string($aiResult['model'] ?? null) ? $aiResult['model'] : null)
                ->setAiRawResult($aiResult)
                ->setAnalysisStatus(AnalysisStatus::AiAssisted);

            return;
        }

        $entry
            ->setAiAnalyzedAt(null)
            ->setAiModel(null)
            ->setAiRawResult(null)
            ->setAnalysisStatus(AnalysisStatus::RulesOnly);
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value));
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: $value;
        } else {
            $value = mb_strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * @param array<int, string> $needles
     *
     * @return array<int, string>
     */
    private function matchNeedles(string $haystack, array $needles): array
    {
        $matches = [];

        foreach ($needles as $needle) {
            $normalized = $this->normalize($needle);
            if ($normalized !== '' && str_contains($haystack, $normalized)) {
                $matches[] = $needle;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param array<int, string> $positiveMatches
     * @param array<int, string> $negativeMatches
     * @param array<int, string> $boostedMatches
     * @param array<int, string> $excludedMatches
     */
    private function scoreRelevance(array $positiveMatches, array $negativeMatches, array $boostedMatches, array $excludedMatches): int
    {
        $score = 20;
        $score += count($positiveMatches) * 15;
        $score += count($boostedMatches) * 20;
        $score -= count($negativeMatches) * 20;
        $score -= count($excludedMatches) * 30;

        return max(0, min(100, $score));
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function scoreClickbait(string $rawTitle, string $normalizedTitle): array
    {
        $signals = [];
        $score = 0;

        foreach (self::CLICKBAIT_PHRASES as $phrase) {
            if (str_contains($normalizedTitle, $this->normalize($phrase))) {
                $signals[] = $phrase;
                $score += 18;
            }
        }

        if (substr_count($rawTitle, '!') >= 2) {
            $signals[] = 'ponctuation excessive';
            $score += 14;
        }

        if (substr_count($rawTitle, '?') >= 2) {
            $signals[] = 'questionnement appuye';
            $score += 10;
        }

        if (preg_match('/\b[A-Z]{5,}\b/u', $rawTitle) === 1) {
            $signals[] = 'mot en majuscules';
            $score += 10;
        }

        if (preg_match('/\b[0-9]+\s+(raisons|choses|secrets)\b/u', $normalizedTitle) === 1) {
            $signals[] = 'liste vague';
            $score += 18;
        }

        if (mb_strlen($normalizedTitle) < 35 && preg_match('/\b(secret|verite|choc|incroyable)\b/u', $normalizedTitle) === 1) {
            $signals[] = 'titre court et sensationnaliste';
            $score += 14;
        }

        return [max(0, min(100, $score)), array_values(array_unique($signals))];
    }

    private function clickbaitLevel(int $score): ClickbaitLevel
    {
        if ($score >= $this->profile->clickbaitThreshold()) {
            return ClickbaitLevel::Clickbait;
        }

        if ($score >= $this->profile->clickbaitSuspicionThreshold()) {
            return ClickbaitLevel::Suspicious;
        }

        return ClickbaitLevel::Clean;
    }

    private function shouldAskAi(int $relevanceScore, int $clickbaitScore): bool
    {
        $minimum = $this->profile->minimumRelevanceScore();
        $ambiguousRelevance = $relevanceScore >= ($minimum - 15) && $relevanceScore <= ($minimum + 15);
        $ambiguousClickbait = $clickbaitScore >= ($this->profile->clickbaitSuspicionThreshold() - 10)
            && $clickbaitScore < $this->profile->clickbaitThreshold();

        return $ambiguousRelevance || $ambiguousClickbait;
    }

    /**
     * @param array<string, mixed>|null $aiResult
     */
    private function decide(int $relevanceScore, ClickbaitLevel $clickbaitLevel, ?array $aiResult): AnalysisDecision
    {
        $parsed = is_array($aiResult['parsed'] ?? null) ? $aiResult['parsed'] : null;
        $confidence = is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : 0.0;
        $suggestedDecision = is_string($parsed['suggestedDecision'] ?? null)
            ? AnalysisDecision::tryFrom($parsed['suggestedDecision'])
            : null;

        if ($suggestedDecision !== null && $confidence >= 0.65) {
            return $suggestedDecision;
        }

        if ($clickbaitLevel === ClickbaitLevel::Clickbait) {
            return AnalysisDecision::Clickbait;
        }

        if ($relevanceScore >= ($this->profile->minimumRelevanceScore() + 20)) {
            return AnalysisDecision::Relevant;
        }

        if ($relevanceScore >= $this->profile->minimumRelevanceScore()) {
            return AnalysisDecision::MaybeRelevant;
        }

        return AnalysisDecision::Ignored;
    }

    /**
     * @param array<int, string> $positiveMatches
     * @param array<int, string> $negativeMatches
     * @param array<int, string> $clickbaitSignals
     * @param array<string, mixed>|null $aiResult
     */
    private function buildReason(AnalysisDecision $decision, array $positiveMatches, array $negativeMatches, array $clickbaitSignals, ?array $aiResult): string
    {
        $parts = ['Decision: '.$decision->label().'.'];

        $parts[] = $positiveMatches === []
            ? 'Aucun signal positif detecte.'
            : 'Signaux positifs: '.implode(', ', $positiveMatches).'.';

        if ($negativeMatches !== []) {
            $parts[] = 'Signaux negatifs: '.implode(', ', $negativeMatches).'.';
        }

        if ($clickbaitSignals !== []) {
            $parts[] = 'Signaux putaclic: '.implode(', ', $clickbaitSignals).'.';
        }

        $parsed = is_array($aiResult['parsed'] ?? null) ? $aiResult['parsed'] : null;
        if ($parsed !== null) {
            $confidence = is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : null;
            $parts[] = 'IA consultee'.($confidence !== null ? ' avec confiance '.number_format($confidence, 2, '.', '') : '').'.';

            if (isset($parsed['reasons']) && is_array($parsed['reasons'])) {
                $reasons = array_filter($parsed['reasons'], 'is_string');
                if ($reasons !== []) {
                    $parts[] = 'Raisons IA: '.implode(', ', $reasons).'.';
                }
            }
        }

        return implode(' ', $parts);
    }
}
