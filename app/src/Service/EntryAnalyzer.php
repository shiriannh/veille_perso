<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
use App\Enum\MediaType;

class EntryAnalyzer
{
    public const VERSION = 'rules-v2';

    /**
     * @var array<string, array<int, string>>
     */
    private const INTERESTS = [
        'media' => ['jeu-video', 'film', 'serie', 'manga', 'anime', 'bd', 'comics', 'livre', 'jdr', 'figurines'],
        'themes' => ['sf', 'science fiction', 'sci-fi', 'fantasy', 'space opera', 'cyberpunk', 'rpg', 'jrpg', 'fps', 'tactique', 'horror', 'horreur'],
        'licenses' => ['battlefield', 'final fantasy', 'warhammer 40k', 'dungeons and dragons', 'pathfinder', 'shadowrun', 'marvel', 'dc comics'],
        'makers' => ['ea', 'electronic arts', 'square enix', 'games workshop'],
        'avoid' => ['people', 'celebrity', 'drama', 'influenceur', 'influencer', 'rumeur people', 'tele realite', 'giveaway', 'concours'],
        'deprioritize' => ['battle pass', 'microtransaction', 'loot box', 'monetisation', 'monetization', 'precommande', 'preorder'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CLICKBAIT_SIGNALS = [
        'fr_sensational' => ['incroyable', 'le choc', 'secret', 'revelation', 'scandale', 'hallucinant'],
        'fr_empty_promise' => ['vous n allez pas croire', 'la verite sur', 'personne n etait pret', 'tout le monde en parle', 'ce que personne ne vous dit', 'incroyable mais vrai'],
        'en_sensational' => ['shocking', 'insane', 'unbelievable', 'mind blowing', 'secret', 'revealed'],
        'en_empty_promise' => ['you won t believe', 'the truth about', 'what nobody tells you', 'everyone is talking about'],
        'rumor_polemic' => ['rumeur', 'rumor', 'controverse', 'controversy', 'polemique', 'clash', 'backlash'],
    ];

    public function __construct(
        private readonly InterestProfile $profile,
        private readonly OptionalAiEntryAnalyzer $aiAnalyzer,
        private readonly EntryTagDetector $entryTagDetector,
    ) {
    }

    public function analyze(Entry $entry, bool $forceAi = false): void
    {
        $this->entryTagDetector->detect($entry);

        $normalizedTitle = $this->normalize($entry->getTitle());
        $normalizedContent = $this->normalize((string) $entry->getRawContent());
        $haystack = trim(implode(' ', array_filter([
            $normalizedTitle,
            $normalizedContent,
            $this->normalize((string) $entry->getOriginalUrl()),
            $this->normalize((string) $entry->getCanonicalUrl()),
            $this->normalize((string) $entry->getSource()?->getName()),
            implode(' ', $entry->getDetectedTags()),
        ])));

        $language = $this->detectLanguage($haystack);
        [$mediaScore, $mediaSignals, $mediaConfidence] = $this->scoreMedia($entry);
        [$thematicScore, $positiveMatches, $negativeMatches, $themeSignals] = $this->scoreTheme($haystack, $entry->getDetectedTags());
        [$editorialQualityScore, $editorialSignals] = $this->scoreEditorialQuality($haystack);
        [$clickbaitScore, $clickbaitSignals] = $this->scoreClickbait($entry->getTitle(), $normalizedTitle);
        $clickbaitLevel = $this->clickbaitLevel($clickbaitScore);

        $relevanceScore = $this->combineRelevance($mediaScore, $thematicScore, $editorialQualityScore, $clickbaitScore);
        $signals = array_merge(
            ['langue: '.$language],
            $mediaSignals,
            $themeSignals,
            $editorialSignals,
            array_map(static fn (string $signal): string => 'putaclic: '.$signal, $clickbaitSignals),
        );

        $aiResult = null;
        if ($forceAi || $this->shouldAskAi($relevanceScore, $clickbaitScore, $editorialQualityScore)) {
            $aiResult = $this->aiAnalyzer->analyze($entry, [
                'language' => $language,
                'mediaScore' => $mediaScore,
                'thematicScore' => $thematicScore,
                'editorialQualityScore' => $editorialQualityScore,
                'relevanceScore' => $relevanceScore,
                'clickbaitScore' => $clickbaitScore,
                'positiveMatches' => $positiveMatches,
                'negativeMatches' => $negativeMatches,
                'detectedTags' => $entry->getDetectedTags(),
                'signals' => $signals,
            ]);
        }

        $decision = $this->decide($relevanceScore, $editorialQualityScore, $clickbaitLevel, $negativeMatches, $aiResult);

        $entry
            ->setNormalizedTitle($normalizedTitle)
            ->setNormalizedContent($normalizedContent !== '' ? $normalizedContent : null)
            ->setMediaDetectionConfidence($mediaConfidence)
            ->setThematicScore($thematicScore)
            ->setEditorialQualityScore($editorialQualityScore)
            ->setAnalysisSignals($signals)
            ->setAnalysisLanguage($language)
            ->setRelevanceScore($relevanceScore)
            ->setClickbaitScore($clickbaitScore)
            ->setClickbaitLevel($clickbaitLevel)
            ->setDecision($decision)
            ->setDecisionReason($this->buildReason($decision, $mediaScore, $thematicScore, $editorialQualityScore, $positiveMatches, $negativeMatches, $clickbaitSignals, $aiResult))
            ->setMatchedPositiveKeywords($positiveMatches)
            ->setMatchedNegativeKeywords($negativeMatches)
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

    /**
     * @return array{0: int, 1: array<int, string>, 2: int}
     */
    private function scoreMedia(Entry $entry): array
    {
        $tags = $entry->getDetectedTags();
        $detectedMediaType = $entry->getDetectedMediaType();
        $signals = [];
        $score = 10;
        $confidence = 0;

        if ($detectedMediaType instanceof MediaType) {
            $signals[] = 'media detecte: '.$detectedMediaType->label();
            $score += 35;
            $confidence += 60;
        }

        foreach (self::INTERESTS['media'] as $tag) {
            if (in_array($tag, $tags, true)) {
                $signals[] = 'media prefere: '.$tag;
                $score += 8;
                $confidence += 8;
            }
        }

        if ($entry->getMediaType() !== MediaType::Other && $detectedMediaType === $entry->getMediaType()) {
            $signals[] = 'media manuel coherent';
            $score += 15;
            $confidence += 15;
        }

        return [$this->clamp($score), $signals, $this->clamp($confidence)];
    }

    /**
     * @param array<int, string> $detectedTags
     *
     * @return array{0: int, 1: array<int, string>, 2: array<int, string>, 3: array<int, string>}
     */
    private function scoreTheme(string $haystack, array $detectedTags): array
    {
        $positive = [];
        $negative = [];
        $signals = [];
        $score = 15;

        foreach (['themes' => 10, 'licenses' => 18, 'makers' => 12] as $group => $weight) {
            foreach (self::INTERESTS[$group] as $needle) {
                if ($this->matches($haystack, $needle) || in_array($this->slug($needle), $detectedTags, true)) {
                    $positive[] = $needle;
                    $signals[] = $group.': '.$needle;
                    $score += $weight;
                }
            }
        }

        foreach (self::INTERESTS['avoid'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $negative[] = $needle;
                $signals[] = 'eviter: '.$needle;
                $score -= 25;
            }
        }

        foreach (self::INTERESTS['deprioritize'] as $needle) {
            if ($this->matches($haystack, $needle) || in_array($this->slug($needle), $detectedTags, true)) {
                $negative[] = $needle;
                $signals[] = 'declassement: '.$needle;
                $score -= 8;
            }
        }

        return [$this->clamp($score), array_values(array_unique($positive)), array_values(array_unique($negative)), $signals];
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function scoreEditorialQuality(string $haystack): array
    {
        $score = 70;
        $signals = [];

        foreach (['source', 'interview', 'critique', 'review', 'analyse', 'analysis', 'preview', 'guide'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $signals[] = 'contenu qualifiant: '.$needle;
                $score += 6;
            }
        }

        foreach (['rumeur', 'rumor', 'leak', 'clash', 'drama', 'controversy', 'polemique'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $signals[] = 'bruit editorial: '.$needle;
                $score -= 12;
            }
        }

        return [$this->clamp($score), $signals];
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function scoreClickbait(string $rawTitle, string $normalizedTitle): array
    {
        $score = 0;
        $signals = [];

        foreach (self::CLICKBAIT_SIGNALS as $group => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->matches($normalizedTitle, $phrase)) {
                    $signals[] = $group.': '.$phrase;
                    $score += str_contains($group, 'empty_promise') ? 22 : 14;
                }
            }
        }

        if (substr_count($rawTitle, '!') >= 2) {
            $signals[] = 'ponctuation excessive';
            $score += 12;
        }

        if (substr_count($rawTitle, '?') >= 2) {
            $signals[] = 'questions repetitives';
            $score += 10;
        }

        if (preg_match('/\b[A-Z]{5,}\b/u', $rawTitle) === 1) {
            $signals[] = 'majuscule insistante';
            $score += 10;
        }

        if (preg_match('/\b[0-9]+\s+(raisons|choses|secrets|reasons|things|secrets)\b/u', $normalizedTitle) === 1) {
            $signals[] = 'liste creuse';
            $score += 16;
        }

        return [$this->clamp($score), array_values(array_unique($signals))];
    }

    private function combineRelevance(int $mediaScore, int $thematicScore, int $editorialQualityScore, int $clickbaitScore): int
    {
        $score = (int) round(($mediaScore * 0.25) + ($thematicScore * 0.50) + ($editorialQualityScore * 0.25));

        if ($clickbaitScore >= 60) {
            $score -= 25;
        } elseif ($clickbaitScore >= 35) {
            $score -= 10;
        }

        return $this->clamp($score);
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

    /**
     * @param array<int, string> $negativeMatches
     * @param array<string, mixed>|null $aiResult
     */
    private function decide(int $relevanceScore, int $editorialQualityScore, ClickbaitLevel $clickbaitLevel, array $negativeMatches, ?array $aiResult): AnalysisDecision
    {
        $parsed = is_array($aiResult['parsed'] ?? null) ? $aiResult['parsed'] : null;
        $confidence = is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : 0.0;
        $suggestedDecision = is_string($parsed['suggestedDecision'] ?? null)
            ? AnalysisDecision::tryFrom($parsed['suggestedDecision'])
            : null;

        if ($suggestedDecision !== null && $confidence >= 0.70) {
            return $suggestedDecision;
        }

        if ($clickbaitLevel === ClickbaitLevel::Clickbait && $relevanceScore < 75) {
            return AnalysisDecision::Clickbait;
        }

        if ($relevanceScore >= 68 && $editorialQualityScore >= 45) {
            return AnalysisDecision::Relevant;
        }

        if ($relevanceScore >= $this->profile->minimumRelevanceScore() && count($negativeMatches) < 3) {
            return AnalysisDecision::MaybeRelevant;
        }

        return AnalysisDecision::Ignored;
    }

    private function shouldAskAi(int $relevanceScore, int $clickbaitScore, int $editorialQualityScore): bool
    {
        return ($relevanceScore >= 38 && $relevanceScore <= 62)
            || ($clickbaitScore >= 25 && $clickbaitScore < 60)
            || ($editorialQualityScore >= 35 && $editorialQualityScore <= 55);
    }

    /**
     * @param array<int, string> $positiveMatches
     * @param array<int, string> $negativeMatches
     * @param array<int, string> $clickbaitSignals
     * @param array<string, mixed>|null $aiResult
     */
    private function buildReason(AnalysisDecision $decision, int $mediaScore, int $thematicScore, int $editorialQualityScore, array $positiveMatches, array $negativeMatches, array $clickbaitSignals, ?array $aiResult): string
    {
        $parts = [
            'Decision: '.$decision->label().'.',
            sprintf('Scores: media %d, thematique %d, qualite editoriale %d.', $mediaScore, $thematicScore, $editorialQualityScore),
        ];

        $parts[] = $positiveMatches === []
            ? 'Aucun centre d interet fort detecte.'
            : 'Correspondances positives: '.implode(', ', $positiveMatches).'.';

        if ($negativeMatches !== []) {
            $parts[] = 'Penalites: '.implode(', ', $negativeMatches).'.';
        }

        if ($clickbaitSignals !== []) {
            $parts[] = 'Signaux clickbait: '.implode(', ', $clickbaitSignals).'.';
        }

        $parsed = is_array($aiResult['parsed'] ?? null) ? $aiResult['parsed'] : null;
        if ($parsed !== null) {
            $confidence = is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : null;
            $parts[] = 'IA consultee'.($confidence !== null ? ' avec confiance '.number_format($confidence, 2, '.', '') : '').'.';
        }

        return implode(' ', $parts);
    }

    private function detectLanguage(string $haystack): string
    {
        $fr = [' le ', ' la ', ' les ', ' des ', ' une ', ' avec ', ' pour ', ' jeux ', ' serie ', ' rumeur ', ' critique '];
        $en = [' the ', ' and ', ' with ', ' for ', ' game ', ' review ', ' rumor ', ' season ', ' trailer ', ' revealed '];
        $frScore = 0;
        $enScore = 0;
        $boxed = ' '.$haystack.' ';

        foreach ($fr as $needle) {
            $frScore += substr_count($boxed, $needle);
        }

        foreach ($en as $needle) {
            $enScore += substr_count($boxed, $needle);
        }

        if ($frScore > $enScore + 1) {
            return 'fr';
        }

        if ($enScore > $frScore + 1) {
            return 'en';
        }

        return 'mixed';
    }

    private function matches(string $haystack, string $needle): bool
    {
        $needle = $this->normalize($needle);

        if ($needle === '') {
            return false;
        }

        if (preg_match('/^[a-z0-9]+$/', $needle) === 1) {
            return preg_match('/(^|\s)'.preg_quote($needle, '/').'($|\s)/', $haystack) === 1;
        }

        return str_contains($haystack, $needle);
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value));
        $value = str_replace(["\r", "\n", "\t", '-', '_', '/'], ' ', $value);

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: $value;
        } else {
            $value = mb_strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9&]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        return str_replace(' ', '-', $this->normalize($value));
    }

    private function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }
}
