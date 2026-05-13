<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\MediaType;
use App\Service\EntryTagDetector;
use PHPUnit\Framework\TestCase;

class EntryTagDetectorTest extends TestCase
{
    public function testItDetectsBattlefieldVideoGameSignals(): void
    {
        $source = (new Source())->setName('Pixel Demo');
        $entry = (new Entry())
            ->setSource($source)
            ->setTitle('Incroyable mais vrai : EA propose Battlefield 6 en precommande')
            ->setRawContent('Jeu video avec gameplay FPS, battle pass, live service et monetisation.');

        (new EntryTagDetector())->detect($entry);

        self::assertSame(MediaType::VideoGame, $entry->getDetectedMediaType());
        self::assertContains('jeu-video', $entry->getDetectedTags());
        self::assertContains('battlefield', $entry->getDetectedTags());
        self::assertContains('battlefield-6', $entry->getDetectedTags());
        self::assertContains('battle-pass', $entry->getDetectedTags());
    }
}
