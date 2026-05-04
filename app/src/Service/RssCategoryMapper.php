<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;

class RssCategoryMapper
{
    /**
     * @return array<string, string>
     */
    public static function mediaMappings(): array
    {
        $mappings = [];
        foreach (self::MEDIA_BY_CATEGORY as $category => $media) {
            $mappings[$category] = $media->value;
        }

        ksort($mappings);

        return $mappings;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function tagMappings(): array
    {
        $mappings = self::TAGS_BY_CATEGORY;
        ksort($mappings);

        return $mappings;
    }

    /**
     * @var array<string, MediaType>
     */
    private const MEDIA_BY_CATEGORY = [
        'roman' => MediaType::Book,
        'romans' => MediaType::Book,
        'roman-vf' => MediaType::Book,
        'romans-vf' => MediaType::Book,
        'roman-vo' => MediaType::Book,
        'romans-vo' => MediaType::Book,
        'livre' => MediaType::Book,
        'livres' => MediaType::Book,
        'novel' => MediaType::Book,
        'novels' => MediaType::Book,
        'book' => MediaType::Book,
        'books' => MediaType::Book,
        'manga' => MediaType::Manga,
        'manhwa' => MediaType::Manhwa,
        'manwha' => MediaType::Manhwa,
        'manhua' => MediaType::Manhua,
        'anime' => MediaType::Anime,
        'comics' => MediaType::Comics,
        'comic' => MediaType::Comics,
        'bd' => MediaType::Bd,
        'bande-dessinee' => MediaType::Bd,
        'jdr' => MediaType::Ttrpg,
        'jeu-de-role' => MediaType::Ttrpg,
        'ttrpg' => MediaType::Ttrpg,
        'figurines' => MediaType::Figurines,
        'miniatures' => MediaType::Figurines,
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

    public function mediaTypeForCategory(string $category): ?MediaType
    {
        return self::MEDIA_BY_CATEGORY[$this->slug($category)] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function tagsForRawCategory(string $category): array
    {
        return $this->tagsForCategory($this->slug($category));
    }

    public function slugCategory(string $category): string
    {
        return $this->slug($category);
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
