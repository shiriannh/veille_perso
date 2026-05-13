<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Enum\AnalysisDecision;
use App\Enum\ClickbaitLevel;
use App\Enum\MediaType;
use App\Service\InterestLevelCalculator;
use PHPUnit\Framework\TestCase;

class InterestLevelCalculatorTest extends TestCase
{
    public function testRelevantAlignedEntryGetsHighInterest(): void
    {
        $entry = (new Entry())
            ->setTitle('Roman SF')
            ->setMediaType(MediaType::Book)
            ->setDetectedMediaType(MediaType::Book)
            ->setDecision(AnalysisDecision::Relevant)
            ->setClickbaitLevel(ClickbaitLevel::Clean)
            ->setRelevanceScore(90)
            ->setMatchedPositiveKeywords(['science-fiction', 'space opera', 'transhumanisme']);

        self::assertGreaterThanOrEqual(4, (new InterestLevelCalculator())->calculate($entry));
    }

    public function testClickbaitIgnoredEntryGetsLowInterest(): void
    {
        $entry = (new Entry())
            ->setTitle('Buzz')
            ->setMediaType(MediaType::Other)
            ->setDecision(AnalysisDecision::Clickbait)
            ->setClickbaitLevel(ClickbaitLevel::Clickbait)
            ->setRelevanceScore(40)
            ->setMatchedNegativeKeywords(['people', 'drama']);

        self::assertSame(0, (new InterestLevelCalculator())->calculate($entry));
    }
}
