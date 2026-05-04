<?php

namespace App\Service;

use App\Entity\Entry;
use App\Enum\MediaType;

class MediaTypeResolver
{
    /**
     * @return array<int, string>
     */
    public static function origins(): array
    {
        return ['manual', 'rss_category', 'detected_tags', 'detected_media_type', 'source_profile', 'title_content', 'imported', 'detected', 'auto', 'unknown'];
    }

    /**
     * @return array<string, array{media: string, label: string, weight: int}>
     */
    public static function tagMediaMappings(): array
    {
        $mappings = [];
        foreach (self::TAG_MEDIA as $tag => $candidate) {
            $mappings[$tag] = [
                'media' => $candidate['media']->value,
                'label' => $candidate['media']->label(),
                'weight' => $candidate['weight'],
            ];
        }

        ksort($mappings);

        return $mappings;
    }

    /**
     * @return array<string, array{media: string, label: string}>
     */
    public static function sourceProfiles(): array
    {
        $profiles = [];
        foreach (self::SOURCE_PROFILES as $needle => $profile) {
            $profiles[$needle] = [
                'media' => $profile['media']->value,
                'label' => $profile['label'],
            ];
        }

        ksort($profiles);

        return $profiles;
    }

    /**
     * @var array<string, array{media: MediaType, weight: int}>
     */
    private const TAG_MEDIA = [
        'jeu-video' => ['media' => MediaType::VideoGame, 'weight' => 40],
        'video-game' => ['media' => MediaType::VideoGame, 'weight' => 40],
        'video_game' => ['media' => MediaType::VideoGame, 'weight' => 40],
        'gaming' => ['media' => MediaType::VideoGame, 'weight' => 24],
        'fps' => ['media' => MediaType::VideoGame, 'weight' => 22],
        'jrpg' => ['media' => MediaType::VideoGame, 'weight' => 22],
        'manga' => ['media' => MediaType::Manga, 'weight' => 45],
        'manhwa' => ['media' => MediaType::Manhwa, 'weight' => 45],
        'manwha' => ['media' => MediaType::Manhwa, 'weight' => 45],
        'manhua' => ['media' => MediaType::Manhua, 'weight' => 45],
        'anime' => ['media' => MediaType::Anime, 'weight' => 45],
        'bd' => ['media' => MediaType::Bd, 'weight' => 42],
        'bande-dessinee' => ['media' => MediaType::Bd, 'weight' => 42],
        'comics' => ['media' => MediaType::Comics, 'weight' => 42],
        'comic' => ['media' => MediaType::Comics, 'weight' => 32],
        'livre' => ['media' => MediaType::Book, 'weight' => 38],
        'roman' => ['media' => MediaType::Book, 'weight' => 38],
        'romans' => ['media' => MediaType::Book, 'weight' => 38],
        'novel' => ['media' => MediaType::Book, 'weight' => 38],
        'book' => ['media' => MediaType::Book, 'weight' => 38],
        'books' => ['media' => MediaType::Book, 'weight' => 38],
        'jdr' => ['media' => MediaType::Ttrpg, 'weight' => 42],
        'jeu-de-role' => ['media' => MediaType::Ttrpg, 'weight' => 42],
        'ttrpg' => ['media' => MediaType::Ttrpg, 'weight' => 42],
        'figurines' => ['media' => MediaType::Figurines, 'weight' => 42],
        'miniatures' => ['media' => MediaType::Figurines, 'weight' => 42],
        'wargame' => ['media' => MediaType::Figurines, 'weight' => 34],
        'film' => ['media' => MediaType::Movie, 'weight' => 38],
        'serie' => ['media' => MediaType::Series, 'weight' => 38],
    ];

    /**
     * @var array<string, array{media: MediaType, label: string}>
     */
    private const SOURCE_PROFILES = [
        'canardpc' => ['media' => MediaType::VideoGame, 'label' => 'profil source jeux video'],
        'canard pc' => ['media' => MediaType::VideoGame, 'label' => 'profil source jeux video'],
        'gamekult' => ['media' => MediaType::VideoGame, 'label' => 'profil source jeux video'],
        'factornews' => ['media' => MediaType::VideoGame, 'label' => 'profil source jeux video'],
        'actugaming' => ['media' => MediaType::VideoGame, 'label' => 'profil source jeux video'],
        'actusf' => ['media' => MediaType::Book, 'label' => 'profil source livres SFF'],
        'noosfere' => ['media' => MediaType::Book, 'label' => 'profil source livres SFF'],
        'belial' => ['media' => MediaType::Book, 'label' => 'profil source livres SFF'],
        'actuabd' => ['media' => MediaType::Bd, 'label' => 'profil source BD'],
        'bdgest' => ['media' => MediaType::Bd, 'label' => 'profil source BD'],
        'manga-news' => ['media' => MediaType::Manga, 'label' => 'profil source manga'],
        'manga news' => ['media' => MediaType::Manga, 'label' => 'profil source manga'],
        'grog' => ['media' => MediaType::Ttrpg, 'label' => 'profil source JDR'],
        'warhammer community' => ['media' => MediaType::Figurines, 'label' => 'profil source figurines'],
    ];

    public function __construct(
        private readonly RssCategoryMapper $rssCategoryMapper,
    ) {
    }

    /**
     * @param array<int, string> $categories
     *
     * @return array{media: ?MediaType, confidence: int, origin: ?string, signals: array<int, string>}
     */
    public function resolve(Entry $entry, array $categories): array
    {
        $scores = [];
        $signals = [];
        $origins = [];

        foreach ($categories as $category) {
            $media = $this->rssCategoryMapper->mediaTypeForCategory($category);
            if ($media instanceof MediaType) {
                $this->addCandidate($scores, $origins, $media, 55, 'rss_category');
                $signals[] = sprintf('media via categorie RSS "%s": %s', $category, $media->label());
            }
        }

        foreach ($entry->getDetectedTags() as $tag) {
            $slug = $this->slug($tag);
            if (!isset(self::TAG_MEDIA[$slug])) {
                continue;
            }

            $candidate = self::TAG_MEDIA[$slug];
            $this->addCandidate($scores, $origins, $candidate['media'], $candidate['weight'], 'detected_tags');
            $signals[] = sprintf('media via tag "%s": %s', $tag, $candidate['media']->label());
        }

        if ($entry->getDetectedMediaType() instanceof MediaType) {
            $this->addCandidate($scores, $origins, $entry->getDetectedMediaType(), 35, 'detected_media_type');
            $signals[] = 'media via detection existante: '.$entry->getDetectedMediaType()->label();
        }

        $sourceText = $this->normalize(implode(' ', array_filter([
            $entry->getSource()?->getName(),
            $entry->getSource()?->getUrl(),
            $entry->getSource()?->getFeedUrl(),
            $entry->getSource()?->getNotes(),
        ])));

        foreach (self::SOURCE_PROFILES as $needle => $profile) {
            if (str_contains($sourceText, $this->normalize($needle))) {
                $this->addCandidate($scores, $origins, $profile['media'], 30, 'source_profile');
                $signals[] = $profile['label'].': '.$profile['media']->label();
            }
        }

        $text = $this->normalize(implode(' ', array_filter([
            $entry->getTitle(),
            $entry->getRawContent(),
            $entry->getOriginalUrl(),
        ])));

        foreach (self::TAG_MEDIA as $tag => $candidate) {
            if (str_contains($text, $this->normalize($tag))) {
                $this->addCandidate($scores, $origins, $candidate['media'], min(18, $candidate['weight']), 'title_content');
            }
        }

        if ($scores === []) {
            return ['media' => null, 'confidence' => 0, 'origin' => null, 'signals' => []];
        }

        arsort($scores);
        $mediaValue = array_key_first($scores);
        $media = MediaType::tryFrom((string) $mediaValue);
        $confidence = min(100, (int) $scores[$mediaValue]);

        return [
            'media' => $media,
            'confidence' => $confidence,
            'origin' => $origins[$mediaValue][0] ?? 'detected',
            'signals' => array_values(array_unique($signals)),
        ];
    }

    /**
     * @param array<string, int> $scores
     * @param array<string, array<int, string>> $origins
     */
    private function addCandidate(array &$scores, array &$origins, MediaType $media, int $weight, string $origin): void
    {
        $scores[$media->value] = ($scores[$media->value] ?? 0) + $weight;
        $origins[$media->value] ??= [];
        $origins[$media->value][] = $origin;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value));

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: $value;
        } else {
            $value = mb_strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        return str_replace(' ', '-', $this->normalize($value));
    }
}
