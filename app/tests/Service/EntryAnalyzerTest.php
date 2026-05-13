<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\FetchMode;
use App\Enum\MediaType;
use App\Enum\SourceType;
use App\Service\EntryAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntryAnalyzerTest extends KernelTestCase
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

    public function testItAnalyzesRssCategoriesAndPromotesFinalMedia(): void
    {
        $source = (new Source())
            ->setName('Observatoire SFF Test')
            ->setType(SourceType::Website)
            ->setFetchMode(FetchMode::Rss)
            ->setSourceProfile('sff_books')
            ->setSourceWeight('high');
        $entry = (new Entry())
            ->setSource($source)
            ->setTitle('Roman SF de space opera')
            ->setMediaType(MediaType::Other)
            ->setRawContent('Un roman de science-fiction francophone sur le transhumanisme.')
            ->setRawPayload(['categories' => ['Romans VF', 'Space Opera', 'Transhumanisme']]);
        $this->entityManager->persist($source);
        $this->entityManager->persist($entry);

        self::getContainer()->get(EntryAnalyzer::class)->analyze($entry);

        self::assertSame(MediaType::Book, $entry->getMediaType());
        self::assertSame(MediaType::Book, $entry->getDetectedMediaType());
        self::assertContains('space-opera', $entry->getDetectedTags());
        self::assertContains($entry->getDecision(), [AnalysisDecision::Relevant, AnalysisDecision::MaybeRelevant], true);
        self::assertSame(AnalysisStatus::RulesOnly, $entry->getAnalysisStatus());
        self::assertNotNull($entry->getAnalyzedAt());
    }
}
