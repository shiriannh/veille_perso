<?php

namespace App\Tests\Repository;

use App\Entity\Entry;
use App\Entity\Review;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\EntryStatus;
use App\Enum\FetchMode;
use App\Enum\MediaType;
use App\Enum\ReviewNextAction;
use App\Enum\ReviewStatus;
use App\Enum\ReviewVerdict;
use App\Enum\SourceType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReviewRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testFindFilteredByStatusMediaAndTag(): void
    {
        $source = (new Source())
            ->setName('Review Repository Source')
            ->setType(SourceType::Website)
            ->setFetchMode(FetchMode::Manual);
        $this->entityManager->persist($source);

        $entry = (new Entry())
            ->setSource($source)
            ->setTitle('Review manga repository')
            ->setMediaType(MediaType::Manga)
            ->setDetectedMediaType(MediaType::Manga)
            ->setDetectedTags(['manga', 'seinen'])
            ->setDecision(AnalysisDecision::Relevant)
            ->setInterestLevel(4)
            ->setStatus(EntryStatus::ToWatch);
        $review = (new Review())
            ->setEntry($entry)
            ->setVerdict(ReviewVerdict::Recommended)
            ->setScore(81)
            ->setStatus(ReviewStatus::ToComplete)
            ->setNextAction(ReviewNextAction::Read);
        $this->entityManager->persist($entry);
        $this->entityManager->persist($review);
        $this->entityManager->flush();

        /** @var ReviewRepository $repository */
        $repository = self::getContainer()->get(ReviewRepository::class);
        $results = $repository->findFiltered([
            'status' => ReviewStatus::ToComplete,
            'mediaType' => MediaType::Manga,
            'detectedTag' => 'manga',
            'sort' => 'score_desc',
        ]);

        self::assertContains($review, $results);
    }
}
