<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Enum\MediaType;
use App\Enum\TagRole;
use App\Service\Slugger;
use App\Service\TagGovernance;
use PHPUnit\Framework\TestCase;

class TagGovernanceTest extends TestCase
{
    public function testItRejectsNoiseTags(): void
    {
        $governance = new TagGovernance(new Slugger());

        self::assertTrue($governance->isStopTag('Festival de Cannes'));
        self::assertSame(TagRole::Noise, $governance->roleFor('nos-conseils'));
    }

    public function testContextualTagNeedsCoOccurrence(): void
    {
        $governance = new TagGovernance(new Slugger());
        $entry = (new Entry())->setMediaType(MediaType::Other);

        self::assertSame([], $governance->validDetectedTags($entry, ['fps']));

        $entry->setMediaType(MediaType::VideoGame);
        self::assertContains('fps', $governance->validDetectedTags($entry, ['fps', 'jeu-video', 'science-fiction']));
    }
}
