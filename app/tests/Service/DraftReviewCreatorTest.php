<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Entity\Review;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\MediaType;
use App\Service\DraftReviewCreator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DraftReviewCreatorTest extends TestCase
{
    public function testItCreatesDraftReviewForHighInterestEntry(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Review::class));

        $entry = (new Entry())
            ->setSource((new Source())->setName('Source demo'))
            ->setTitle('Signal fort')
            ->setMediaType(MediaType::Book)
            ->setOriginalUrl('https://example.test/signal')
            ->setDecision(AnalysisDecision::Relevant)
            ->setRelevanceScore(86)
            ->setInterestLevel(4)
            ->setMatchedPositiveKeywords(['space opera']);

        $review = (new DraftReviewCreator($entityManager))->createIfNeeded($entry);

        self::assertInstanceOf(Review::class, $review);
        self::assertTrue($review->isDraft());
        self::assertTrue($review->isAutoCreated());
        self::assertSame($review, $entry->getReview());
    }

    public function testItDoesNotDuplicateExistingReview(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $entry = (new Entry())
            ->setTitle('Deja fiche')
            ->setInterestLevel(5);
        $entry->setReview((new Review())->setEntry($entry));

        self::assertNull((new DraftReviewCreator($entityManager))->createIfNeeded($entry));
    }
}
