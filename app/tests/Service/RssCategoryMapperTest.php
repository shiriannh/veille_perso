<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Enum\MediaType;
use App\Service\RssCategoryMapper;
use App\Service\Slugger;
use App\Service\TagGovernance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RssCategoryMapperTest extends TestCase
{
    private RssCategoryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RssCategoryMapper(new TagGovernance(new Slugger()));
    }

    #[DataProvider('mediaCategoryProvider')]
    public function testItMapsRssCategoriesToMedia(string $category, MediaType $expectedMedia): void
    {
        self::assertSame($expectedMedia, $this->mapper->mediaTypeForCategory($category));
    }

    /**
     * @return iterable<string, array{0: string, 1: MediaType}>
     */
    public static function mediaCategoryProvider(): iterable
    {
        yield 'Romans VF' => ['Romans VF', MediaType::Book];
        yield 'Manga' => ['Manga', MediaType::Manga];
        yield 'Bande dessinee' => ['Bande dessinee', MediaType::Bd];
        yield 'Comics' => ['Comics', MediaType::Comics];
        yield 'TTRPG' => ['TTRPG', MediaType::Ttrpg];
    }

    public function testItAddsUsefulTagsFromRssCategories(): void
    {
        $entry = (new Entry())->setMediaType(MediaType::Other);

        $this->mapper->enrich($entry, ['Romans VF', 'Space Opera', 'Transhumanisme']);

        self::assertSame(MediaType::Book, $entry->getDetectedMediaType());
        self::assertContains('vf', $entry->getDetectedTags());
        self::assertContains('space-opera', $entry->getDetectedTags());
        self::assertContains('transhumanisme', $entry->getDetectedTags());
    }
}
