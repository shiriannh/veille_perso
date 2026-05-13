<?php

namespace App\Tests\Repository;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
use App\Enum\FetchMode;
use App\Enum\MediaType;
use App\Enum\SourceType;
use App\Repository\EntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntryRepositoryTest extends KernelTestCase
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

    public function testFindFilteredByMediaDecisionAndDetectedTag(): void
    {
        $source = $this->source('Repository Source');
        $wanted = $this->entry($source, 'Roman SF repository', MediaType::Book, AnalysisDecision::Relevant, ['sf', 'space-opera']);
        $this->entry($source, 'Jeu ignore repository', MediaType::VideoGame, AnalysisDecision::Ignored, ['jeu-video']);
        $this->entityManager->flush();

        /** @var EntryRepository $repository */
        $repository = self::getContainer()->get(EntryRepository::class);
        $results = $repository->findFiltered([
            'mediaType' => MediaType::Book,
            'decision' => AnalysisDecision::Relevant,
            'detectedTag' => 'sf',
            'sort' => 'relevance_desc',
        ]);

        self::assertContains($wanted, $results);
        self::assertCount(1, array_filter($results, static fn (Entry $entry): bool => str_contains($entry->getTitle(), 'repository')));
    }

    private function source(string $name): Source
    {
        $source = (new Source())
            ->setName($name)
            ->setType(SourceType::Website)
            ->setFetchMode(FetchMode::Rss)
            ->setUrl('https://example.test')
            ->setFeedUrl('https://example.test/feed.xml');

        $this->entityManager->persist($source);

        return $source;
    }

    /**
     * @param array<int, string> $tags
     */
    private function entry(Source $source, string $title, MediaType $mediaType, AnalysisDecision $decision, array $tags): Entry
    {
        $entry = (new Entry())
            ->setSource($source)
            ->setTitle($title)
            ->setMediaType($mediaType)
            ->setDetectedMediaType($mediaType)
            ->setDetectedTags($tags)
            ->setDecision($decision)
            ->setClickbaitLevel(ClickbaitLevel::Clean)
            ->setRelevanceScore($decision === AnalysisDecision::Relevant ? 80 : 20)
            ->setAnalysisStatus(AnalysisStatus::RulesOnly)
            ->setAnalyzedAt(new \DateTimeImmutable());

        $this->entityManager->persist($entry);

        return $entry;
    }
}
