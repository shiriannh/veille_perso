<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;

class EntryTagDetector
{
    /**
     * Dictionnaire centralise : ajouter ici les tags et leurs variantes.
     *
     * @var array<string, array<int, string>>
     */
    private const TAG_DICTIONARY = [
        'jeu-video' => ['jeu video', 'jeux video', 'gaming', 'gameplay', 'joueurs', 'console', 'pc gamer', 'steam', 'playstation', 'xbox', 'nintendo'],
        'serie' => ['serie', 'saison', 'episode', 'showrunner'],
        'film' => ['film', 'cinema', 'long metrage', 'realisateur'],
        'manga' => ['manga', 'mangas', 'shonen', 'seinen'],
        'manwha' => ['manwha', 'manhwa'],
        'manhua' => ['manhua'],
        'anime' => ['anime', 'animation japonaise'],
        'livre' => ['livre', 'roman', 'auteur', 'editeur'],
        'bd' => ['bd', 'bande dessinee'],
        'comics' => ['comics', 'comic book'],
        'jdr' => ['jdr', 'jeu de role', 'roleplaying game', 'rpg papier'],
        'figurines' => ['figurine', 'figurines', 'miniatures'],
        'sf' => ['sf', 'science fiction', 'science-fiction', 'sci fi', 'sci-fi'],
        'fantasy' => ['fantasy', 'fantaisie', 'heroic fantasy'],
        'space-opera' => ['space opera', 'space-opera'],
        'cyberpunk' => ['cyberpunk'],
        'horreur' => ['horreur', 'horror', 'epouvante'],
        'action' => ['action'],
        'rpg' => ['rpg', 'jeu de role'],
        'fps' => ['fps', 'first person shooter', 'tir a la premiere personne'],
        'jrpg' => ['jrpg'],
        'tactique' => ['tactique', 'tactical', 'strategie au tour par tour'],
        'live-service' => ['live service', 'live-service', 'service game', 'saisons'],
        'ea' => ['ea', 'electronic arts'],
        'battlefield' => ['battlefield'],
        'battlefield-6' => ['battlefield 6', 'battlefield vi'],
        'battle-pass' => ['battle pass', 'battle-pass', 'season pass'],
        'precommande' => ['precommande', 'precommander', 'pre-order', 'preorder'],
        'monetisation' => ['monetisation', 'microtransaction', 'microtransactions', 'loot box', 'loot boxes'],
        'square-enix' => ['square enix', 'square-enix'],
        'final-fantasy' => ['final fantasy'],
        'warhammer-40k' => ['warhammer 40k', 'warhammer 40000', 'warhammer 40 000'],
        'games-workshop' => ['games workshop'],
        'dungeons-and-dragons' => ['dungeons and dragons', 'donjons et dragons', 'd&d'],
        'pathfinder' => ['pathfinder'],
        'shadowrun' => ['shadowrun'],
        'marvel' => ['marvel'],
        'dc' => ['dc comics', 'dc universe'],
        'sensationnaliste' => ['incroyable', 'vous n allez pas croire', 'le choc', 'secret', 'la verite sur'],
    ];

    /**
     * @var array<string, MediaType>
     */
    private const MEDIA_TYPE_BY_TAG = [
        'jeu-video' => MediaType::VideoGame,
        'rpg' => MediaType::VideoGame,
        'fps' => MediaType::VideoGame,
        'jrpg' => MediaType::VideoGame,
        'tactique' => MediaType::VideoGame,
        'film' => MediaType::Movie,
        'serie' => MediaType::Series,
        'manga' => MediaType::Comic,
        'bd' => MediaType::Comic,
        'comics' => MediaType::Comic,
        'jdr' => MediaType::Other,
        'figurines' => MediaType::Other,
        'livre' => MediaType::ScienceFictionNovel,
        'space-opera' => MediaType::SpaceOperaNovel,
        'fantasy' => MediaType::FantasyNovel,
    ];

    public function detect(Entry $entry): void
    {
        $tags = $this->detectTags($entry);
        $entry
            ->setDetectedTags($tags)
            ->setDetectedMediaType($this->detectMediaType($tags));
    }

    /**
     * @return array<int, string>
     */
    private function detectTags(Entry $entry): array
    {
        $haystack = $this->normalize(implode(' ', array_filter([
            $entry->getTitle(),
            $entry->getRawContent(),
            $entry->getOriginalUrl(),
            $entry->getCanonicalUrl(),
            $entry->getSource()?->getName(),
        ])));

        $tags = [];

        foreach (self::TAG_DICTIONARY as $tag => $needles) {
            foreach ($needles as $needle) {
                if ($this->containsNeedle($haystack, $needle)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        sort($tags);

        return array_values(array_unique($tags));
    }

    /**
     * @param array<int, string> $tags
     */
    private function detectMediaType(array $tags): ?MediaType
    {
        foreach ($tags as $tag) {
            if (isset(self::MEDIA_TYPE_BY_TAG[$tag])) {
                return self::MEDIA_TYPE_BY_TAG[$tag];
            }
        }

        return null;
    }

    private function containsNeedle(string $haystack, string $needle): bool
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
}
