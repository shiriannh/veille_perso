<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
use App\Enum\MediaType;

class EntryAnalyzer
{
    public const VERSION = 'rules-v4';

    /**
     * Dictionnaire metier centralise. Les blocs restent explicites pour eviter
     * qu'un score global masque la raison d'une decision.
     *
     * @var array<string, array<int, string>>
     */
    private const INTERESTS = [
        'media' => ['jeu-video', 'film', 'serie', 'manga', 'manhwa', 'manhua', 'anime', 'bd', 'comics', 'livre', 'jdr', 'figurines'],
        'themes_fr' => ['sf', 'science fiction', 'fantastique', 'fantasy', 'space opera', 'cyberpunk', 'horreur', 'jeu de role', 'strategie', 'tactique'],
        'themes_en' => ['sci-fi', 'science fiction', 'fantasy', 'space opera', 'cyberpunk', 'horror', 'roleplaying', 'strategy', 'tactical'],
        'genres' => ['rpg', 'jrpg', 'fps', 'tactique', 'live-service', 'space-opera'],
        'licenses' => ['battlefield', 'final fantasy', 'warhammer 40k', 'dungeons and dragons', 'pathfinder', 'shadowrun', 'marvel', 'dc comics'],
        'makers' => ['ea', 'electronic arts', 'square enix', 'games workshop'],
        'avoid' => ['people', 'celebrity', 'drama', 'influenceur', 'influencer', 'rumeur people', 'tele realite', 'giveaway', 'concours'],
        'deprioritize' => ['battle pass', 'microtransaction', 'loot box', 'monetisation', 'monetization', 'precommande', 'preorder'],
    ];

    /**
     * Profils prudents : ils renforcent le score et les raisons, mais ne
     * remplacent pas une valeur manuelle.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SOURCE_PROFILES = [
        'sff_books' => [
            'label' => 'source SFF / romans',
            'needles' => ['actusf', 'belial', 'noosfere', 'elbakine', 'l-atalante', 'mnemos', 'bragelonne', 'rivages imaginaire'],
            'media' => ['book', 'science_fiction_novel', 'fantasy_novel', 'space_opera_novel'],
            'primaryMedia' => 'book',
            'tags' => ['livre', 'sf', 'fantasy', 'space-opera'],
            'relevanceBonus' => 24,
            'mediaBonus' => 28,
        ],
        'manga_sources' => [
            'label' => 'source manga',
            'needles' => ['manga news', 'manga-news', 'manganews', 'sanctuary manga'],
            'media' => ['manga'],
            'primaryMedia' => 'manga',
            'tags' => ['manga'],
            'relevanceBonus' => 22,
            'mediaBonus' => 28,
        ],
        'sequential_art' => [
            'label' => 'source BD / manga / comics',
            'needles' => ['actuabd', 'bdgest', 'bdzoom', 'du9', 'comixtrip'],
            'media' => ['bd', 'manga', 'comics'],
            'primaryMedia' => 'bd',
            'tags' => ['bd', 'manga', 'comics'],
            'relevanceBonus' => 22,
            'mediaBonus' => 26,
        ],
        'video_games' => [
            'label' => 'source jeux video',
            'needles' => ['canard pc', 'canardpc', 'gamekult', 'factornews', 'actugaming', 'jeuxvideo', 'pc gamer', 'ign', 'rock paper shotgun'],
            'media' => ['video_game'],
            'tags' => ['jeu-video', 'rpg', 'fps'],
            'relevanceBonus' => 22,
            'mediaBonus' => 30,
        ],
        'ttrpg' => [
            'label' => 'source JDR',
            'needles' => ['jdr', 'roliste', 'grog', 'black book', 'ttrpg', 'roleplaying'],
            'media' => ['ttrpg'],
            'tags' => ['jdr'],
            'relevanceBonus' => 20,
            'mediaBonus' => 28,
        ],
        'miniatures' => [
            'label' => 'source figurines / hobby',
            'needles' => ['warhammer community', 'games workshop', 'figurines', 'miniatures', 'hobby'],
            'media' => ['figurines'],
            'tags' => ['figurines', 'warhammer-40k', 'games-workshop'],
            'relevanceBonus' => 20,
            'mediaBonus' => 28,
        ],
        'noisy_generalist' => [
            'label' => 'source generaliste bruyante',
            'needles' => [],
            'media' => [],
            'tags' => [],
            'relevanceBonus' => -10,
            'mediaBonus' => 0,
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CLICKBAIT_SIGNALS = [
        'fr_sensational' => ['incroyable', 'incroyable mais vrai', 'le choc', 'secret', 'revelation', 'scandale', 'hallucinant'],
        'fr_empty_promise' => ['vous n allez pas croire', 'la verite sur', 'personne n etait pret', 'tout le monde en parle', 'ce que personne ne vous dit'],
        'en_sensational' => ['shocking', 'insane', 'unbelievable', 'mind blowing', 'secret', 'revealed'],
        'en_empty_promise' => ['you won t believe', 'the truth about', 'what nobody tells you', 'everyone is talking about'],
        'rumor_polemic' => ['rumeur', 'rumor', 'controverse', 'controversy', 'polemique', 'clash', 'backlash'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const LANGUAGE_MARKERS = [
        'fr' => [' le ', ' la ', ' les ', ' des ', ' une ', ' avec ', ' pour ', ' dans ', ' sur ', ' cette ', ' nouveau ', ' critique ', ' roman ', ' bande dessinee '],
        'en' => [' the ', ' and ', ' with ', ' for ', ' from ', ' this ', ' new ', ' review ', ' season ', ' trailer ', ' revealed ', ' novel ', ' comic '],
    ];

    public function __construct(
        private readonly InterestProfile $profile,
        private readonly OptionalAiEntryAnalyzer $aiAnalyzer,
        private readonly EntryTagDetector $entryTagDetector,
        private readonly RssCategoryMapper $rssCategoryMapper,
        private readonly MediaTypeResolver $mediaTypeResolver,
        private readonly AutoTagEnricher $autoTagEnricher,
        private readonly InterestLevelCalculator $interestLevelCalculator,
        private readonly DraftReviewCreator $draftReviewCreator,
        private readonly ReferenceFieldSynchronizer $referenceFieldSynchronizer,
    ) {
    }

    public function analyze(Entry $entry, bool $forceAi = false): void
    {
        $this->entryTagDetector->detect($entry);
        $categories = $this->rssCategories($entry);
        $this->rssCategoryMapper->enrich($entry, $categories);
        $sourceProfile = $this->sourceProfile($entry);
        $this->promoteReliableSourceMedia($entry, $sourceProfile, $categories);
        $mediaResolution = $this->mediaTypeResolver->resolve($entry, $categories);
        $this->promoteFinalMediaType($entry, $mediaResolution);

        $normalizedTitle = $this->normalize($entry->getTitle());
        $normalizedContent = $this->normalize((string) $entry->getRawContent());
        $categorySlugs = array_map(fn (string $category): string => $this->rssCategoryMapper->slugCategory($category), $categories);
        $haystack = trim(implode(' ', array_filter([
            $normalizedTitle,
            $normalizedContent,
            $this->normalize((string) $entry->getOriginalUrl()),
            $this->normalize((string) $entry->getCanonicalUrl()),
            $this->normalize((string) $entry->getSource()?->getName()),
            $this->normalize((string) $entry->getSource()?->getUrl()),
            $this->normalize((string) $entry->getSource()?->getFeedUrl()),
            implode(' ', $entry->getDetectedTags()),
            implode(' ', $categorySlugs),
        ])));

        $language = $this->detectLanguage($haystack);
        [$mediaScore, $mediaSignals, $mediaConfidence] = $this->scoreMedia($entry, $sourceProfile, $categories);
        [$thematicScore, $positiveMatches, $negativeMatches, $themeSignals] = $this->scoreTheme($haystack, $entry->getDetectedTags(), $sourceProfile, $categorySlugs, $language);
        [$editorialQualityScore, $editorialSignals] = $this->scoreEditorialQuality($haystack);
        [$clickbaitScore, $clickbaitSignals] = $this->scoreClickbait($entry->getTitle(), $normalizedTitle);
        $clickbaitLevel = $this->clickbaitLevel($clickbaitScore);

        $sourceProfileBonus = $this->sourceProfileBonus($sourceProfile);
        $relevanceScore = $this->combineRelevance($mediaScore, $thematicScore, $editorialQualityScore, $clickbaitScore, $sourceProfileBonus);
        $signals = array_merge(
            ['langue: '.$language],
            $categories === [] ? [] : ['categories RSS exploitees: '.implode(', ', $categorySlugs)],
            $mediaSignals,
            $mediaResolution['signals'],
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
                'sourceProfile' => $sourceProfile['label'] ?? null,
                'rssCategories' => $categories,
                'positiveMatches' => $positiveMatches,
                'negativeMatches' => $negativeMatches,
                'detectedTags' => $entry->getDetectedTags(),
                'signals' => $signals,
            ]);
        }

        $decision = $this->decide($relevanceScore, $mediaScore, $thematicScore, $editorialQualityScore, $clickbaitLevel, $negativeMatches, $sourceProfileBonus, $aiResult);

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

        $entry->setInterestLevel($this->interestLevelCalculator->calculate($entry));
        $this->referenceFieldSynchronizer->syncEntryToReferences($entry);
        $this->autoTagEnricher->enrich($entry, array_merge($categories, $entry->getDetectedTags()));
        $this->draftReviewCreator->createIfNeeded($entry);

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
     * @param array<string, mixed>|null $sourceProfile
     * @param array<int, string> $categories
     *
     * @return array{0: int, 1: array<int, string>, 2: int}
     */
    private function scoreMedia(Entry $entry, ?array $sourceProfile, array $categories): array
    {
        $tags = $entry->getDetectedTags();
        $detectedMediaType = $entry->getDetectedMediaType();
        $finalMediaType = $entry->getFinalMediaType();
        $signals = [];
        $score = 18;
        $confidence = 0;

        if ($finalMediaType !== MediaType::Other) {
            $signals[] = 'media final: '.$finalMediaType->label();
            $score += 42;
            $confidence += max(35, (int) ($entry->getMediaDetectionConfidence() ?? 0));
        }

        if ($detectedMediaType instanceof MediaType) {
            $signals[] = 'media detecte: '.$detectedMediaType->label();
            $score += $detectedMediaType === $finalMediaType ? 14 : 8;
            $confidence += $detectedMediaType === $finalMediaType ? 18 : 10;
        }

        foreach ($categories as $category) {
            $media = $this->rssCategoryMapper->mediaTypeForCategory($category);
            if ($media instanceof MediaType) {
                $signals[] = 'media renforce par categorie RSS: '.$media->label();
                $score += 12;
                $confidence += 14;
            }
        }

        if ($sourceProfile !== null) {
            $signals[] = 'media renforce par profil source: '.$sourceProfile['label'];
            $score += (int) $sourceProfile['mediaBonus'];
            $confidence += 22;
        }

        foreach (self::INTERESTS['media'] as $tag) {
            if (in_array($tag, $tags, true)) {
                $signals[] = 'media prefere: '.$tag;
                $score += 6;
                $confidence += 6;
            }
        }

        if ($entry->getMediaTypeOrigin() === 'manual') {
            $signals[] = 'media final manuel';
            $score += 12;
            $confidence += 12;
        }

        return [$this->clamp($score), array_values(array_unique($signals)), $this->clamp($confidence)];
    }

    /**
     * @param array<int, string> $detectedTags
     * @param array<string, mixed>|null $sourceProfile
     * @param array<int, string> $categorySlugs
     *
     * @return array{0: int, 1: array<int, string>, 2: array<int, string>, 3: array<int, string>}
     */
    private function scoreTheme(string $haystack, array $detectedTags, ?array $sourceProfile, array $categorySlugs, string $language): array
    {
        $positive = [];
        $negative = [];
        $signals = [];
        $score = 24;

        $themeGroups = ['genres' => 9, 'licenses' => 16, 'makers' => 11];
        $themeGroups[$language === 'en' ? 'themes_en' : 'themes_fr'] = 10;
        if ($language === 'mixed' || $language === 'unknown') {
            $themeGroups['themes_fr'] = 8;
            $themeGroups['themes_en'] = 8;
        }

        foreach ($themeGroups as $group => $weight) {
            foreach (self::INTERESTS[$group] as $needle) {
                if ($this->matches($haystack, $needle) || in_array($this->slug($needle), $detectedTags, true) || in_array($this->slug($needle), $categorySlugs, true)) {
                    $positive[] = $needle;
                    $signals[] = $group.': '.$needle;
                    $score += $weight;
                }
            }
        }

        foreach (($this->profile->weightedPositiveTerms() + $this->profile->weightedBoostedPhrases()) as $needle => $weight) {
            if ($this->matches($haystack, $needle) || in_array($this->slug($needle), $detectedTags, true) || in_array($this->slug($needle), $categorySlugs, true)) {
                $positive[] = $needle;
                $signals[] = 'profil interet: '.$needle;
                $score += $weight;
            }
        }

        foreach ($categorySlugs as $slug) {
            if ($slug !== '' && !in_array($slug, $detectedTags, true)) {
                $signals[] = 'categorie RSS thematique: '.$slug;
                $score += 5;
            }
        }

        if ($sourceProfile !== null) {
            $signals[] = 'bonus pertinence via profil source: '.$sourceProfile['label'];
            $score += (int) $sourceProfile['relevanceBonus'];
            foreach ($sourceProfile['tags'] as $tag) {
                $positive[] = (string) $tag;
            }
        }

        foreach (self::INTERESTS['avoid'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $negative[] = $needle;
                $signals[] = 'eviter: '.$needle;
                $score -= 18;
            }
        }

        foreach (($this->profile->weightedNegativeTerms() + $this->profile->weightedExcludedPhrases()) as $needle => $weight) {
            if ($this->matches($haystack, $needle)) {
                $negative[] = $needle;
                $signals[] = 'profil exclusion: '.$needle;
                $score -= $weight;
            }
        }

        foreach (self::INTERESTS['deprioritize'] as $needle) {
            if ($this->matches($haystack, $needle) || in_array($this->slug($needle), $detectedTags, true)) {
                $negative[] = $needle;
                $signals[] = 'declassement leger: '.$needle;
                $score -= 5;
            }
        }

        return [$this->clamp($score), array_values(array_unique($positive)), array_values(array_unique($negative)), array_values(array_unique($signals))];
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    private function scoreEditorialQuality(string $haystack): array
    {
        $score = 72;
        $signals = [];

        foreach (['source', 'interview', 'critique', 'review', 'analyse', 'analysis', 'preview', 'guide', 'dossier', 'entretien', 'chronique'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $signals[] = 'contenu qualifiant: '.$needle;
                $score += 5;
            }
        }

        foreach (['rumeur', 'rumor', 'leak', 'clash', 'drama', 'controversy', 'polemique'] as $needle) {
            if ($this->matches($haystack, $needle)) {
                $signals[] = 'bruit editorial: '.$needle;
                $score -= 10;
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
                    $score += str_contains($group, 'empty_promise') ? 20 : 12;
                }
            }
        }

        if (substr_count($rawTitle, '!') >= 2) {
            $signals[] = 'ponctuation excessive';
            $score += 10;
        }

        if (substr_count($rawTitle, '?') >= 2) {
            $signals[] = 'questions repetitives';
            $score += 8;
        }

        if (preg_match('/\b[A-Z]{5,}\b/u', $rawTitle) === 1) {
            $signals[] = 'majuscule insistante';
            $score += 8;
        }

        if (preg_match('/\b[0-9]+\s+(raisons|choses|secrets|reasons|things|secrets)\b/u', $normalizedTitle) === 1) {
            $signals[] = 'liste creuse';
            $score += 14;
        }

        return [$this->clamp($score), array_values(array_unique($signals))];
    }

    private function combineRelevance(int $mediaScore, int $thematicScore, int $editorialQualityScore, int $clickbaitScore, int $sourceProfileBonus): int
    {
        $score = (int) round(($mediaScore * 0.34) + ($thematicScore * 0.46) + ($editorialQualityScore * 0.20));

        if ($sourceProfileBonus > 0) {
            $score += min(8, (int) round($sourceProfileBonus / 4));
        }

        if ($clickbaitScore >= 60) {
            $score -= 12;
        } elseif ($clickbaitScore >= 35) {
            $score -= 5;
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
    private function decide(int $relevanceScore, int $mediaScore, int $thematicScore, int $editorialQualityScore, ClickbaitLevel $clickbaitLevel, array $negativeMatches, int $sourceProfileBonus, ?array $aiResult): AnalysisDecision
    {
        $parsed = is_array($aiResult['parsed'] ?? null) ? $aiResult['parsed'] : null;
        $confidence = is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : 0.0;
        $suggestedDecision = is_string($parsed['suggestedDecision'] ?? null)
            ? AnalysisDecision::tryFrom($parsed['suggestedDecision'])
            : null;

        if ($suggestedDecision !== null && $confidence >= 0.70) {
            return $suggestedDecision;
        }

        if ($clickbaitLevel === ClickbaitLevel::Clickbait && $relevanceScore < 45 && $sourceProfileBonus === 0) {
            return AnalysisDecision::Clickbait;
        }

        if ($relevanceScore >= 62 && $editorialQualityScore >= 38) {
            return AnalysisDecision::Relevant;
        }

        if (count($negativeMatches) >= 4 && $relevanceScore < 48 && $sourceProfileBonus === 0) {
            return AnalysisDecision::Ignored;
        }

        if (
            $relevanceScore >= max(35, $this->profile->minimumRelevanceScore() - 8)
            || ($mediaScore >= 58 && $thematicScore >= 35)
            || ($sourceProfileBonus >= 20 && $mediaScore >= 38)
        ) {
            return AnalysisDecision::MaybeRelevant;
        }

        return AnalysisDecision::Ignored;
    }

    private function shouldAskAi(int $relevanceScore, int $clickbaitScore, int $editorialQualityScore): bool
    {
        return ($relevanceScore >= 36 && $relevanceScore <= 58)
            || ($clickbaitScore >= 30 && $clickbaitScore < 60)
            || ($editorialQualityScore >= 35 && $editorialQualityScore <= 52);
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
            : 'Correspondances positives: '.implode(', ', array_slice($positiveMatches, 0, 12)).'.';

        if ($negativeMatches !== []) {
            $parts[] = 'Penalites: '.implode(', ', array_slice($negativeMatches, 0, 8)).'.';
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

    /**
     * @return array<int, string>
     */
    private function rssCategories(Entry $entry): array
    {
        $rawPayload = $entry->getRawPayload();

        return is_array($rawPayload) && isset($rawPayload['categories']) && is_array($rawPayload['categories'])
            ? array_values(array_filter($rawPayload['categories'], 'is_string'))
            : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceProfile(Entry $entry): ?array
    {
        $source = $entry->getSource();
        $sourceProfile = $source?->getSourceProfile();
        if (is_string($sourceProfile) && isset(self::SOURCE_PROFILES[$sourceProfile])) {
            return $this->weightedSourceProfile(self::SOURCE_PROFILES[$sourceProfile], $source?->getSourceWeight() ?? 'normal');
        }

        $haystack = $this->normalize(implode(' ', array_filter([
            $source?->getName(),
            $source?->getUrl(),
            $source?->getFeedUrl(),
            $source?->getNotes(),
        ])));

        foreach (self::SOURCE_PROFILES as $profile) {
            foreach ($profile['needles'] as $needle) {
                if ($this->matches($haystack, (string) $needle)) {
                    return $this->weightedSourceProfile($profile, $source?->getSourceWeight() ?? 'normal');
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $sourceProfile
     * @param array<int, string> $categories
     */
    private function promoteReliableSourceMedia(Entry $entry, ?array $sourceProfile, array $categories): void
    {
        if ($entry->getDetectedMediaType() instanceof MediaType || $sourceProfile === null || $this->hasCategoryMedia($categories)) {
            return;
        }

        $primaryMedia = $sourceProfile['primaryMedia'] ?? null;
        if (is_string($primaryMedia)) {
            $detected = MediaType::tryFrom($primaryMedia);
            if ($detected instanceof MediaType) {
                $entry->setDetectedMediaType($detected);
            }

            return;
        }

        $media = $sourceProfile['media'];
        if (is_array($media) && count($media) === 1) {
            $detected = MediaType::tryFrom((string) reset($media));
            if ($detected instanceof MediaType) {
                $entry->setDetectedMediaType($detected);
            }
        }
    }

    /**
     * @param array{media: ?MediaType, confidence: int, origin: ?string, signals: array<int, string>} $mediaResolution
     */
    private function promoteFinalMediaType(Entry $entry, array $mediaResolution): void
    {
        $media = $mediaResolution['media'];
        if (!$media instanceof MediaType || $media === MediaType::Other || $mediaResolution['confidence'] < 35) {
            return;
        }

        if ($entry->getDetectedMediaType() === null || $mediaResolution['confidence'] >= (int) ($entry->getMediaDetectionConfidence() ?? 0)) {
            $entry
                ->setDetectedMediaType($media)
                ->setMediaDetectionConfidence($mediaResolution['confidence']);
        }

        if ($entry->getMediaType() !== MediaType::Other && $entry->getMediaTypeOrigin() === 'manual') {
            return;
        }

        if ($entry->getMediaType() === MediaType::Other || in_array($entry->getMediaTypeOrigin(), [null, 'unknown', 'imported', 'detected', 'auto'], true)) {
            $entry
                ->setMediaType($media)
                ->setMediaTypeOrigin($mediaResolution['origin'] ?? 'detected');
        }
    }

    /**
     * @param array<string, mixed>|null $sourceProfile
     */
    private function sourceProfileBonus(?array $sourceProfile): int
    {
        return $sourceProfile === null ? 0 : (int) $sourceProfile['relevanceBonus'];
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    private function weightedSourceProfile(array $profile, string $sourceWeight): array
    {
        $multiplier = match ($sourceWeight) {
            'low' => 0.65,
            'high' => 1.35,
            'noisy' => 0.45,
            default => 1.0,
        };

        $profile['relevanceBonus'] = (int) round(((int) $profile['relevanceBonus']) * $multiplier);
        $profile['mediaBonus'] = (int) round(((int) $profile['mediaBonus']) * $multiplier);
        $profile['label'] = $profile['label'].' / poids '.$sourceWeight;

        return $profile;
    }

    /**
     * @param array<int, string> $categories
     */
    private function hasCategoryMedia(array $categories): bool
    {
        foreach ($categories as $category) {
            if ($this->rssCategoryMapper->mediaTypeForCategory($category) instanceof MediaType) {
                return true;
            }
        }

        return false;
    }

    private function detectLanguage(string $haystack): string
    {
        $boxed = ' '.$haystack.' ';
        $frScore = 0;
        $enScore = 0;

        foreach (self::LANGUAGE_MARKERS['fr'] as $needle) {
            $frScore += substr_count($boxed, $needle);
        }

        foreach (self::LANGUAGE_MARKERS['en'] as $needle) {
            $enScore += substr_count($boxed, $needle);
        }

        if ($frScore === 0 && $enScore === 0) {
            return 'unknown';
        }

        if ($frScore >= $enScore + 1) {
            return 'fr';
        }

        if ($enScore >= $frScore + 1) {
            return 'en';
        }

        return $frScore >= 2 && $enScore >= 2 ? 'mixed' : ($frScore >= $enScore ? 'fr' : 'en');
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
