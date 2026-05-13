<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;
use App\Enum\TagRole;

class TagGovernance
{
    /**
     * Tags forts : ils qualifient directement le perimetre de veille.
     *
     * @var array<string, TagRole>
     */
    private const ROLES = [
        'science-fiction' => TagRole::Pivot,
        'sf' => TagRole::Pivot,
        'fantasy' => TagRole::Pivot,
        'fantastique' => TagRole::Pivot,
        'space-opera' => TagRole::Pivot,
        'cyberpunk' => TagRole::Pivot,
        'manga' => TagRole::Pivot,
        'anime' => TagRole::Pivot,
        'jdr' => TagRole::Pivot,
        'figurines' => TagRole::Pivot,
        'jeu-video' => TagRole::Pivot,
        'warhammer-40k' => TagRole::Pivot,
        'dungeons-and-dragons' => TagRole::Pivot,
        'pathfinder' => TagRole::Pivot,
        'shadowrun' => TagRole::Pivot,
        'fps' => TagRole::Contextual,
        'rpg' => TagRole::Contextual,
        'jrpg' => TagRole::Contextual,
        'interview' => TagRole::Contextual,
        'adaptation' => TagRole::Contextual,
        'festival' => TagRole::EditorialFormat,
        'trailer' => TagRole::EditorialFormat,
        'battle-pass' => TagRole::Deprioritize,
        'microtransaction' => TagRole::Deprioritize,
        'monetisation' => TagRole::Deprioritize,
        'crowdfunding' => TagRole::Deprioritize,
        'gamefound' => TagRole::Deprioritize,
    ];

    /**
     * @var array<int, string>
     */
    private const STOP_TAGS = [
        'nos-conseils',
        'actualites',
        'actualite',
        'blockbuster',
        'download',
        'essai',
        'evenement',
        'nouveau',
        'nouvelle',
        'nouvelles',
        'nouveaute',
        'novella',
        'podcast',
        'postface',
        'preface',
        'prix-litteraire',
        'festival',
        'festival-de-cannes',
        'interview',
        'evenement-fnac-gratuit',
        'rpg-party',
        'gamefound',
        'crowdfunding',
        'douglas-kennedy',
        'virginie-grimaldi',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CO_OCCURRENCES = [
        'fps' => ['jeu-video', 'science-fiction', 'sf', 'fantasy', 'warhammer-40k', 'battlefield'],
        'rpg' => ['jeu-video', 'jdr', 'fantasy', 'science-fiction', 'sf'],
        'jrpg' => ['jeu-video', 'manga', 'anime', 'fantasy', 'science-fiction', 'sf'],
        'interview' => ['jeu-video', 'livre', 'manga', 'bd', 'comics', 'jdr', 'figurines', 'science-fiction', 'fantasy'],
        'festival' => ['film', 'cinema', 'science-fiction', 'fantasy'],
        'crowdfunding' => ['figurines', 'jdr', 'jeu-video', 'warhammer-40k'],
        'gamefound' => ['figurines', 'jdr', 'jeu-video'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const MEDIA_COMPATIBILITY = [
        'book' => ['livre', 'roman', 'science-fiction', 'sf', 'fantasy', 'space-opera', 'fantastique'],
        'science_fiction_novel' => ['livre', 'roman', 'science-fiction', 'sf', 'space-opera'],
        'fantasy_novel' => ['livre', 'roman', 'fantasy'],
        'space_opera_novel' => ['livre', 'roman', 'space-opera', 'science-fiction', 'sf'],
        'video_game' => ['jeu-video', 'fps', 'rpg', 'jrpg', 'battlefield', 'battle-pass', 'live-service'],
        'manga' => ['manga'],
        'anime' => ['anime'],
        'bd' => ['bd'],
        'comics' => ['comics'],
        'ttrpg' => ['jdr', 'jeu-de-role', 'ttrpg', 'dungeons-and-dragons', 'pathfinder', 'shadowrun'],
        'figurines' => ['figurines', 'miniatures', 'warhammer-40k', 'games-workshop'],
    ];

    public function __construct(private readonly Slugger $slugger)
    {
    }

    public function roleFor(string $labelOrSlug): TagRole
    {
        $slug = $this->slug($labelOrSlug);

        if (in_array($slug, self::STOP_TAGS, true)) {
            return TagRole::Noise;
        }

        return self::ROLES[$slug] ?? (preg_match('/^[a-z]+-[a-z]+$/', $slug) === 1 ? TagRole::Entity : TagRole::Contextual);
    }

    public function isStopTag(string $labelOrSlug): bool
    {
        return in_array($this->slug($labelOrSlug), self::STOP_TAGS, true);
    }

    /**
     * @param array<int, string> $candidateLabels
     *
     * @return array<int, string>
     */
    public function validDetectedTags(Entry $entry, array $candidateLabels): array
    {
        $rawSlugs = array_values(array_unique(array_map(fn (string $label): string => $this->slug($label), $candidateLabels)));
        $valid = [];

        foreach ($rawSlugs as $slug) {
            if ($slug === '' || $this->isStopTag($slug) || !$this->passesContext($entry, $slug, $rawSlugs)) {
                continue;
            }

            $valid[] = $slug;
        }

        sort($valid);

        return $valid;
    }

    /**
     * @param array<int, string> $candidateLabels
     *
     * @return array{allowed: bool, confidence: int, role: TagRole, reason: string}
     */
    public function evaluateAutoCreation(Entry $entry, string $candidateLabel, array $candidateLabels): array
    {
        $slug = $this->slug($candidateLabel);
        $rawSlugs = array_values(array_unique(array_map(fn (string $label): string => $this->slug($label), $candidateLabels)));
        $role = $this->roleFor($slug);

        if ($slug === '' || strlen($slug) < 3 || $this->isStopTag($slug)) {
            return ['allowed' => false, 'confidence' => 0, 'role' => TagRole::Noise, 'reason' => 'stop-tag ou terme trop faible'];
        }

        if (!$this->passesContext($entry, $slug, $rawSlugs)) {
            return ['allowed' => false, 'confidence' => 0, 'role' => $role, 'reason' => 'contexte insuffisant ou cooccurrence absente'];
        }

        $score = $this->confidenceScore($entry, $slug, $rawSlugs, $role);

        return [
            'allowed' => $score >= 75,
            'confidence' => $score,
            'role' => $role,
            'reason' => $score >= 75 ? 'contexte metier suffisant' : 'confiance trop basse',
        ];
    }

    public function scoringWeight(string $slug): int
    {
        return match ($this->roleFor($slug)) {
            TagRole::Pivot => 10,
            TagRole::Contextual => 3,
            TagRole::Deprioritize => -6,
            TagRole::Entity => 1,
            TagRole::EditorialFormat, TagRole::Noise => 0,
        };
    }

    /**
     * @return array<int, string>
     */
    public function stopTags(): array
    {
        return self::STOP_TAGS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function coOccurrences(): array
    {
        return self::CO_OCCURRENCES;
    }

    private function passesContext(Entry $entry, string $slug, array $rawSlugs): bool
    {
        $role = $this->roleFor($slug);
        if ($role === TagRole::Noise) {
            return false;
        }

        if ($role === TagRole::Pivot) {
            return true;
        }

        if (isset(self::CO_OCCURRENCES[$slug]) && count(array_intersect($rawSlugs, self::CO_OCCURRENCES[$slug])) === 0) {
            return false;
        }

        if ($this->isCompatibleWithMedia($entry->getFinalMediaType(), $slug) || $this->isCompatibleWithMedia($entry->getDetectedMediaType(), $slug)) {
            return true;
        }

        return count(array_filter($rawSlugs, fn (string $candidate): bool => $this->roleFor($candidate) === TagRole::Pivot)) > 0;
    }

    private function confidenceScore(Entry $entry, string $slug, array $rawSlugs, TagRole $role): int
    {
        $score = match ($role) {
            TagRole::Pivot => 68,
            TagRole::Contextual => 45,
            TagRole::Deprioritize => 40,
            TagRole::Entity => 28,
            TagRole::EditorialFormat => 25,
            TagRole::Noise => 0,
        };

        $score += count(array_filter($rawSlugs, fn (string $candidate): bool => $this->roleFor($candidate) === TagRole::Pivot)) * 8;
        $score += $this->isCompatibleWithMedia($entry->getFinalMediaType(), $slug) ? 14 : 0;
        $score += $this->isCompatibleWithMedia($entry->getDetectedMediaType(), $slug) ? 10 : 0;
        $score += ($entry->getRelevanceScore() ?? 0) >= 55 ? 8 : 0;
        $score += $entry->getMatchedPositiveKeywords() !== [] ? 6 : 0;

        return max(0, min(100, $score));
    }

    private function isCompatibleWithMedia(?MediaType $mediaType, string $slug): bool
    {
        if (!$mediaType instanceof MediaType) {
            return false;
        }

        return in_array($slug, self::MEDIA_COMPATIBILITY[$mediaType->value] ?? [], true);
    }

    private function slug(string $value): string
    {
        return $this->slugger->slug($value);
    }
}
