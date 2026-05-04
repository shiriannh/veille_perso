<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\MediaType;
use App\Service\MediaTypeResolver;
use App\Service\RssCategoryMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaTypeResolverTest extends TestCase
{
    private MediaTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MediaTypeResolver(new RssCategoryMapper());
    }

    /**
     * @param array<int, string> $tags
     */
    #[DataProvider('detectedTagProvider')]
    public function testItPromotesMediaFromDetectedTags(array $tags, MediaType $expectedMedia): void
    {
        $entry = $this->entry(tags: $tags);

        $resolution = $this->resolver->resolve($entry, []);

        self::assertSame($expectedMedia, $resolution['media']);
        self::assertSame('detected_tags', $resolution['origin']);
        self::assertGreaterThanOrEqual(35, $resolution['confidence']);
    }

    /**
     * @return iterable<string, array{0: array<int, string>, 1: MediaType}>
     */
    public static function detectedTagProvider(): iterable
    {
        yield 'jeu-video' => [['jeu-video'], MediaType::VideoGame];
        yield 'video_game' => [['video_game'], MediaType::VideoGame];
        yield 'manga' => [['manga'], MediaType::Manga];
        yield 'anime' => [['anime'], MediaType::Anime];
        yield 'bd' => [['bd'], MediaType::Bd];
        yield 'comics' => [['comics'], MediaType::Comics];
        yield 'book' => [['book'], MediaType::Book];
        yield 'roman' => [['roman'], MediaType::Book];
        yield 'ttrpg' => [['ttrpg'], MediaType::Ttrpg];
        yield 'figurines' => [['figurines'], MediaType::Figurines];
    }

    /**
     * @param array<int, string> $categories
     */
    #[DataProvider('rssCategoryProvider')]
    public function testItPromotesMediaFromRssCategories(array $categories, MediaType $expectedMedia): void
    {
        $entry = $this->entry();

        $resolution = $this->resolver->resolve($entry, $categories);

        self::assertSame($expectedMedia, $resolution['media']);
        self::assertSame('rss_category', $resolution['origin']);
        self::assertGreaterThanOrEqual(55, $resolution['confidence']);
    }

    /**
     * @return iterable<string, array{0: array<int, string>, 1: MediaType}>
     */
    public static function rssCategoryProvider(): iterable
    {
        yield 'Romans VF' => [['Romans VF'], MediaType::Book];
        yield 'Romans VF plus Space Opera' => [['Romans VF', 'Space Opera'], MediaType::Book];
        yield 'Manga' => [['Manga'], MediaType::Manga];
        yield 'Bande dessinee' => [['Bande dessinee'], MediaType::Bd];
        yield 'Comics' => [['Comics'], MediaType::Comics];
        yield 'TTRPG' => [['TTRPG'], MediaType::Ttrpg];
    }

    /**
     * @param array<int, string> $tags
     */
    private function entry(array $tags = []): Entry
    {
        $source = (new Source())->setName('Source de test');

        return (new Entry())
            ->setSource($source)
            ->setTitle('Titre de test')
            ->setMediaType(MediaType::Other)
            ->setDetectedTags($tags);
    }
}
