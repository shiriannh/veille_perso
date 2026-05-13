<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;
use App\Enum\TagRole;

class ContentAdmissionPolicy
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const SOURCE_HINTS = [
        'sff_books' => [
            'label' => 'Livres SFF',
            'expectedMediaTypes' => ['book', 'science_fiction_novel', 'fantasy_novel', 'space_opera_novel'],
            'expectedPivotTags' => ['science-fiction', 'sf', 'fantasy', 'fantastique', 'space-opera', 'cyberpunk'],
            'deprioritizedTags' => ['crowdfunding', 'gamefound'],
            'noiseTags' => ['nouvelle', 'nouvelles', 'novella', 'essai', 'preface', 'postface', 'interview', 'podcast', 'actualites', 'nouveau', 'nos-conseils', 'festival', 'prix-litteraire', 'evenement', 'blockbuster', 'download'],
            'admissionBias' => 18,
            'sourceConfidence' => 22,
            'strictness' => 'normal',
        ],
        'video_games' => [
            'label' => 'Jeux video',
            'expectedMediaTypes' => ['video_game'],
            'expectedPivotTags' => ['jeu-video', 'science-fiction', 'sf', 'fantasy', 'cyberpunk', 'warhammer-40k', 'battlefield', 'final-fantasy'],
            'deprioritizedTags' => ['battle-pass', 'microtransaction', 'monetisation', 'crowdfunding'],
            'noiseTags' => ['interview', 'podcast', 'actualites', 'nouveau', 'festival', 'download'],
            'admissionBias' => 14,
            'sourceConfidence' => 20,
            'strictness' => 'normal',
        ],
        'sequential_art' => [
            'label' => 'BD / manga / comics',
            'expectedMediaTypes' => ['bd', 'manga', 'comics', 'anime'],
            'expectedPivotTags' => ['bd', 'manga', 'comics', 'anime', 'science-fiction', 'sf', 'fantasy', 'cyberpunk'],
            'deprioritizedTags' => [],
            'noiseTags' => ['nouveau', 'nos-conseils', 'festival', 'actualites'],
            'admissionBias' => 14,
            'sourceConfidence' => 18,
            'strictness' => 'normal',
        ],
        'manga_sources' => [
            'label' => 'Manga',
            'expectedMediaTypes' => ['manga', 'anime'],
            'expectedPivotTags' => ['manga', 'anime', 'science-fiction', 'sf', 'fantasy', 'cyberpunk'],
            'deprioritizedTags' => [],
            'noiseTags' => ['nouveau', 'actualites'],
            'admissionBias' => 16,
            'sourceConfidence' => 20,
            'strictness' => 'normal',
        ],
        'ttrpg' => [
            'label' => 'JDR',
            'expectedMediaTypes' => ['ttrpg'],
            'expectedPivotTags' => ['jdr', 'dungeons-and-dragons', 'pathfinder', 'shadowrun', 'fantasy', 'science-fiction', 'sf'],
            'deprioritizedTags' => ['crowdfunding', 'gamefound'],
            'noiseTags' => ['actualites', 'nouveau', 'festival'],
            'admissionBias' => 16,
            'sourceConfidence' => 20,
            'strictness' => 'normal',
        ],
        'miniatures' => [
            'label' => 'Figurines',
            'expectedMediaTypes' => ['figurines'],
            'expectedPivotTags' => ['figurines', 'warhammer-40k', 'games-workshop', 'science-fiction', 'sf', 'fantasy'],
            'deprioritizedTags' => ['crowdfunding', 'gamefound'],
            'noiseTags' => ['actualites', 'nouveau'],
            'admissionBias' => 16,
            'sourceConfidence' => 20,
            'strictness' => 'normal',
        ],
        'noisy_generalist' => [
            'label' => 'Generaliste bruyant',
            'expectedMediaTypes' => [],
            'expectedPivotTags' => ['science-fiction', 'sf', 'fantasy', 'space-opera', 'jeu-video', 'manga', 'jdr', 'figurines'],
            'deprioritizedTags' => ['battle-pass', 'microtransaction', 'monetisation', 'crowdfunding', 'gamefound'],
            'noiseTags' => ['nouvelle', 'nouvelles', 'novella', 'essai', 'preface', 'postface', 'interview', 'podcast', 'actualites', 'nouveau', 'nos-conseils', 'festival', 'prix-litteraire', 'evenement', 'blockbuster', 'download'],
            'admissionBias' => -18,
            'sourceConfidence' => -8,
            'strictness' => 'strict',
        ],
        'none' => [
            'label' => 'Aucun',
            'expectedMediaTypes' => [],
            'expectedPivotTags' => ['science-fiction', 'sf', 'fantasy', 'space-opera', 'jeu-video', 'manga', 'jdr', 'figurines'],
            'deprioritizedTags' => [],
            'noiseTags' => ['nouvelle', 'nouvelles', 'novella', 'essai', 'preface', 'postface', 'interview', 'podcast', 'actualites', 'nouveau', 'nos-conseils', 'festival', 'prix-litteraire', 'evenement', 'blockbuster', 'download'],
            'admissionBias' => 10,
            'sourceConfidence' => 0,
            'strictness' => 'normal',
        ],
    ];

    public function __construct(
        private readonly EntryTagDetector $entryTagDetector,
        private readonly RssCategoryMapper $rssCategoryMapper,
        private readonly MediaTypeResolver $mediaTypeResolver,
        private readonly TagGovernance $tagGovernance,
    ) {
    }

    /**
     * @param array<int, string> $categories
     */
    public function evaluate(Entry $entry, array $categories = []): ContentAdmissionResult
    {
        $this->entryTagDetector->detect($entry);
        $this->rssCategoryMapper->enrich($entry, $categories);

        $resolution = $this->mediaTypeResolver->resolve($entry, $categories);
        if ($entry->getMediaType() === MediaType::Other && $resolution['media'] instanceof MediaType) {
            $entry
                ->setMediaType($resolution['media'])
                ->setDetectedMediaType($resolution['media'])
                ->setMediaDetectionConfidence($resolution['confidence'])
                ->setMediaTypeOrigin($resolution['origin'] ?? 'admission');
        }

        $profileKey = $entry->getSource()?->getSourceProfile() ?? 'none';
        $hints = self::SOURCE_HINTS[$profileKey] ?? self::SOURCE_HINTS['none'];
        $tags = $entry->getDetectedTags();
        $rawTerms = $entry->getRawDetectedTerms();
        $media = $entry->getFinalMediaType();
        $signals = ['profil source: '.$hints['label']];
        $score = (int) $hints['admissionBias'] + (int) $hints['sourceConfidence'];

        $expectedMediaMatch = $media !== MediaType::Other && in_array($media->value, $hints['expectedMediaTypes'], true);
        if ($expectedMediaMatch) {
            $score += 34;
            $signals[] = 'media attendu: '.$media->label();
        } elseif ($media !== MediaType::Other) {
            $score += 14;
            $signals[] = 'media reconnu hors profil: '.$media->label();
        }

        $pivotTags = array_values(array_filter($tags, fn (string $tag): bool => $this->tagGovernance->roleFor($tag) === TagRole::Pivot));
        $expectedPivots = array_values(array_intersect($tags, $hints['expectedPivotTags']));
        if ($expectedPivots !== []) {
            $score += 32 + (min(3, count($expectedPivots)) * 4);
            $signals[] = 'pivot attendu: '.implode(', ', array_slice($expectedPivots, 0, 4));
        } elseif ($pivotTags !== []) {
            $score += 20;
            $signals[] = 'pivot reconnu: '.implode(', ', array_slice($pivotTags, 0, 4));
        }

        $deprioritized = array_values(array_intersect($tags, $hints['deprioritizedTags']));
        if ($deprioritized !== []) {
            $score -= 10;
            $signals[] = 'signal declassant: '.implode(', ', $deprioritized);
        }

        $noise = array_values(array_filter($rawTerms, fn (string $term): bool => $this->tagGovernance->isStopTag($term) || in_array($term, $hints['noiseTags'], true)));
        if ($noise !== []) {
            $score -= min(24, count($noise) * 6);
            $signals[] = 'bruit neutralise: '.implode(', ', array_slice($noise, 0, 5));
        }

        $strict = $hints['strictness'] === 'strict';
        $score = max(0, min(100, $score));
        $hasStrongBusinessSignal = $expectedMediaMatch && $expectedPivots !== [];

        if ($hasStrongBusinessSignal || (!$strict && $score >= 58 && ($media !== MediaType::Other || $pivotTags !== []))) {
            return new ContentAdmissionResult(ContentAdmissionResult::ADMIT, $score, 'media et signaux metier suffisants', $signals);
        }

        if (($strict && $score >= 62 && $pivotTags !== []) || (!$strict && $score >= 38 && ($media !== MediaType::Other || $pivotTags !== []))) {
            return new ContentAdmissionResult(ContentAdmissionResult::QUARANTINE, $score, 'signaux partiels a revoir', $signals);
        }

        return new ContentAdmissionResult(ContentAdmissionResult::REJECT, $score, 'signaux insuffisants avant creation Entry', $signals);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sourceHints(): array
    {
        return self::SOURCE_HINTS;
    }
}
