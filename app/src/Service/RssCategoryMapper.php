<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;

class RssCategoryMapper
{
    /**
     * @var array<string, MediaType>
     */
    private const MEDIA_BY_CATEGORY = [
        'roman' => MediaType::ScienceFictionNovel,
        'romans' => MediaType::ScienceFictionNovel,
        'roman-vf' => MediaType::ScienceFictionNovel,
        'romans-vf' => MediaType::ScienceFictionNovel,
        'roman-vo' => MediaType::ScienceFictionNovel,
        'romans-vo' => MediaType::ScienceFictionNovel,
        'livre' => MediaType::ScienceFictionNovel,
        'livres' => MediaType::ScienceFictionNovel,
        'novel' => MediaType::ScienceFictionNovel,
        'novels' => MediaType::ScienceFictionNovel,
        'book' => MediaType::ScienceFictionNovel,
        'books' => MediaType::ScienceFictionNovel,
        'manga' => MediaType::Comic,
        'anime' => MediaType::Series,
        'comics' => MediaType::Comic,
        'bd' => MediaType::Comic,
        'bande-dessinee' => MediaType::Comic,
        'jdr' => MediaType::Other,
        'jeu-de-role' => MediaType::Other,
        'ttrpg' => MediaType::Other,
        'figurines' => MediaType::Other,
        'miniatures' => MediaType::Other,
        'jeu-video' => MediaType::VideoGame,
        'jeux-video' => MediaType::VideoGame,
        'video-game' => MediaType::VideoGame,
        'video-games' => MediaType::VideoGame,
        'film' => MediaType::Movie,
        'films' => MediaType::Movie,
        'serie' => MediaType::Series,
        'series' => MediaType::Series,
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const TAGS_BY_CATEGORY = [
        'roman-vf' => ['vf'],
        'romans-vf' => ['vf'],
        'roman-vo' => ['vo'],
        'romans-vo' => ['vo'],
        'space-opera' => ['space-opera'],
        'transhumanisme' => ['transhumanisme'],
        'transhumanism' => ['transhumanisme'],
        'science-fiction' => ['sf'],
        'sci-fi' => ['sf'],
        'fantasy' => ['fantasy'],
        'cyberpunk' => ['cyberpunk'],
        'vf' => ['vf'],
        'vo' => ['vo'],
    ];

    /**
     * @param array<int, string> $categories
     */
    public function enrich(Entry $entry, array $categories): void
    {
        $tags = $entry->getDetectedTags();

        foreach ($categories as $category) {
            $slug = $this->slug($category);
            if ($slug === '') {
                continue;
            }

            if ($entry->getDetectedMediaType() === null && isset(self::MEDIA_BY_CATEGORY[$slug])) {
                $entry->setDetectedMediaType(self::MEDIA_BY_CATEGORY[$slug]);
            }

            foreach ($this->tagsForCategory($slug) as $tag) {
                $tags[] = $tag;
            }
        }

        $entry->setDetectedTags($tags);
    }

    /**
     * @return array<int, string>
     */
    private function tagsForCategory(string $slug): array
    {
        if (isset(self::TAGS_BY_CATEGORY[$slug])) {
            return self::TAGS_BY_CATEGORY[$slug];
        }

        if (isset(self::MEDIA_BY_CATEGORY[$slug])) {
            return [];
        }

        return [$slug];
    }

    private function slug(string $value): string
    {
        $value = html_entity_decode(strip_tags($value));

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: $value;
        } else {
            $value = mb_strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
