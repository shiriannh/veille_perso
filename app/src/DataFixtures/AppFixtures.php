<?php

namespace App\DataFixtures;

use App\Entity\Entry;
use App\Entity\ImportRun;
use App\Entity\Review;
use App\Entity\Source;
use App\Entity\SynthesisReport;
use App\Entity\Tag;
use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
use App\Enum\EntryStatus;
use App\Enum\FetchMode;
use App\Enum\ImportRunStatus;
use App\Enum\MediaType;
use App\Enum\ReviewNextAction;
use App\Enum\ReviewStatus;
use App\Enum\ReviewVerdict;
use App\Enum\SourceType;
use App\Service\ReferenceDataSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly ReferenceDataSeeder $referenceDataSeeder,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->referenceDataSeeder->seed(false);

        $sources = [
            'Observatoire SFF Demo' => (new Source())
                ->setName('Observatoire SFF Demo')
                ->setType(SourceType::Website)
                ->setFetchMode(FetchMode::Rss)
                ->setUrl('https://demo.local/sff')
                ->setFeedUrl('https://demo.local/sff/feed.xml')
                ->setSourceProfile('sff_books')
                ->setSourceWeight('high')
                ->setImportPriority(80)
                ->setNotes('Source fictive pour tester les romans SFF.'),
            'Pixel Local Demo' => (new Source())
                ->setName('Pixel Local Demo')
                ->setType(SourceType::Website)
                ->setFetchMode(FetchMode::Rss)
                ->setUrl('https://demo.local/games')
                ->setFeedUrl('https://demo.local/games/feed.xml')
                ->setSourceProfile('video_games')
                ->setSourceWeight('normal')
                ->setImportPriority(70)
                ->setNotes('Source fictive jeux video.'),
            'Cases et Bulles Demo' => (new Source())
                ->setName('Cases et Bulles Demo')
                ->setType(SourceType::Website)
                ->setFetchMode(FetchMode::Manual)
                ->setUrl('https://demo.local/bd')
                ->setSourceProfile('sequential_art')
                ->setNotes('Source fictive BD / manga / comics.'),
        ];

        foreach ($sources as $source) {
            $manager->persist($source);
        }

        $entries = [
            (new Entry())
                ->setSource($sources['Observatoire SFF Demo'])
                ->setTitle('Un nouveau space opera francophone explore le transhumanisme')
                ->setMediaType(MediaType::Book)
                ->setDetectedMediaType(MediaType::Book)
                ->setMediaTypeOrigin('rss_category')
                ->setMediaDetectionConfidence(86)
                ->setOriginalUrl('https://demo.local/sff/space-opera')
                ->setRawContent('Roman de science-fiction avec voyage interstellaire, enjeux politiques et transhumanisme.')
                ->setRawPayload(['categories' => ['Romans VF', 'Space Opera', 'Transhumanisme']])
                ->setDetectedTags(['livre', 'sf', 'space-opera', 'transhumanisme', 'vf'])
                ->setMatchedPositiveKeywords(['space opera', 'science-fiction', 'transhumanisme'])
                ->setDecision(AnalysisDecision::Relevant)
                ->setDecisionReason('Source SFF, categories RSS explicites et tags alignes.')
                ->setClickbaitLevel(ClickbaitLevel::Clean)
                ->setRelevanceScore(84)
                ->setClickbaitScore(4)
                ->setThematicScore(88)
                ->setEditorialQualityScore(76)
                ->setAnalysisSignals(['media via categorie RSS', 'bonus source SFF'])
                ->setAnalysisLanguage('fr')
                ->setAnalysisVersion('fixture-demo')
                ->setAnalyzedAt(new \DateTimeImmutable('-2 days'))
                ->setAnalysisStatus(AnalysisStatus::RulesOnly)
                ->setInterestLevel(5)
                ->setImportedAt(new \DateTimeImmutable('-3 days'))
                ->setPublishedAt(new \DateTimeImmutable('-4 days'))
                ->setStatus(EntryStatus::ToWatch),
            (new Entry())
                ->setSource($sources['Pixel Local Demo'])
                ->setTitle('Battlefield 6 detaille son mode tactique et son battle pass')
                ->setMediaType(MediaType::VideoGame)
                ->setDetectedMediaType(MediaType::VideoGame)
                ->setMediaTypeOrigin('detected_tags')
                ->setMediaDetectionConfidence(78)
                ->setOriginalUrl('https://demo.local/games/battlefield-6')
                ->setRawContent('FPS, live service, precommande et monetisation a surveiller.')
                ->setDetectedTags(['jeu-video', 'fps', 'battlefield', 'battlefield-6', 'battle-pass', 'live-service'])
                ->setMatchedPositiveKeywords(['battlefield', 'fps'])
                ->setMatchedNegativeKeywords(['battle pass'])
                ->setDecision(AnalysisDecision::MaybeRelevant)
                ->setDecisionReason('Signal sectoriel pertinent, mais bruit commercial a verifier.')
                ->setClickbaitLevel(ClickbaitLevel::Suspicious)
                ->setRelevanceScore(63)
                ->setClickbaitScore(38)
                ->setThematicScore(70)
                ->setEditorialQualityScore(58)
                ->setAnalysisSignals(['source jeux video', 'penalite battle pass'])
                ->setAnalysisLanguage('fr')
                ->setAnalysisVersion('fixture-demo')
                ->setAnalyzedAt(new \DateTimeImmutable('-1 day'))
                ->setAnalysisStatus(AnalysisStatus::RulesOnly)
                ->setInterestLevel(3)
                ->setImportedAt(new \DateTimeImmutable('-1 day'))
                ->setStatus(EntryStatus::ToWatch),
            (new Entry())
                ->setSource($sources['Cases et Bulles Demo'])
                ->setTitle('Panorama manga et comics pour la rentree')
                ->setMediaType(MediaType::Manga)
                ->setDetectedMediaType(MediaType::Manga)
                ->setMediaTypeOrigin('source_profile')
                ->setDetectedTags(['manga', 'comics', 'bd'])
                ->setDecision(AnalysisDecision::Relevant)
                ->setDecisionReason('Profil source sequentiel et tags explicites.')
                ->setClickbaitLevel(ClickbaitLevel::Clean)
                ->setRelevanceScore(72)
                ->setClickbaitScore(8)
                ->setAnalysisLanguage('fr')
                ->setAnalysisVersion('fixture-demo')
                ->setAnalyzedAt(new \DateTimeImmutable('-5 hours'))
                ->setAnalysisStatus(AnalysisStatus::RulesOnly)
                ->setInterestLevel(4)
                ->setStatus(EntryStatus::SafeBet),
            (new Entry())
                ->setSource($sources['Pixel Local Demo'])
                ->setTitle('Vous n allez pas croire cette polemique de stars')
                ->setMediaType(MediaType::Other)
                ->setMediaTypeOrigin('unknown')
                ->setDetectedTags(['sensationnaliste'])
                ->setMatchedNegativeKeywords(['people', 'polemique'])
                ->setDecision(AnalysisDecision::Ignored)
                ->setDecisionReason('Hors perimetre culturel suivi.')
                ->setClickbaitLevel(ClickbaitLevel::Clickbait)
                ->setRelevanceScore(18)
                ->setClickbaitScore(76)
                ->setAnalysisLanguage('fr')
                ->setAnalysisVersion('fixture-demo')
                ->setAnalyzedAt(new \DateTimeImmutable('-6 hours'))
                ->setAnalysisStatus(AnalysisStatus::RulesOnly)
                ->setInterestLevel(0)
                ->setImportedAt(new \DateTimeImmutable('-12 days'))
                ->setStatus(EntryStatus::Ignore),
        ];

        foreach ($entries as $entry) {
            $manager->persist($entry);
        }

        $review = (new Review())
            ->setEntry($entries[0])
            ->setSummary('Roman demo a fort potentiel pour la veille SFF.')
            ->setStrengths('Space opera, SF francophone, thematiques transhumanistes.')
            ->setWeaknesses('Verifier les premiers retours critiques.')
            ->setPersonalNote('Fixture anonyme.')
            ->setVerdict(ReviewVerdict::Curious)
            ->setScore(82)
            ->setStatus(ReviewStatus::Draft)
            ->setNextAction(ReviewNextAction::Read)
            ->setIsAutoCreated(true);

        $activeReview = (new Review())
            ->setEntry($entries[2])
            ->setSummary('Panorama utile pour reperer des sorties sequentielles.')
            ->setVerdict(ReviewVerdict::Recommended)
            ->setScore(74)
            ->setStatus(ReviewStatus::ToComplete)
            ->setNextAction(ReviewNextAction::Monitor);

        $manager->persist($review);
        $manager->persist($activeReview);

        $manager->persist((new ImportRun())
            ->setSource($sources['Observatoire SFF Demo'])
            ->setStartedAt(new \DateTimeImmutable('-3 days'))
            ->setFinishedAt(new \DateTimeImmutable('-3 days +2 minutes'))
            ->setStatus(ImportRunStatus::Success)
            ->setFetchedCount(12)
            ->setCreatedCount(3)
            ->setSkippedCount(9));
        $manager->persist((new ImportRun())
            ->setSource($sources['Pixel Local Demo'])
            ->setStartedAt(new \DateTimeImmutable('-1 day'))
            ->setFinishedAt(new \DateTimeImmutable('-1 day +1 minute'))
            ->setStatus(ImportRunStatus::Failed)
            ->setFetchedCount(0)
            ->setErrorMessage('Erreur fictive pour tester le statut local.'));

        foreach ([
            ['Science-fiction', 'science-fiction', 'fr', MediaType::Book],
            ['Space opera', 'space-opera', 'fr', MediaType::Book],
            ['Battlefield', 'battlefield', null, MediaType::VideoGame],
            ['Manga', 'manga', null, MediaType::Manga],
        ] as [$name, $slug, $language, $media]) {
            $manager->persist((new Tag())
                ->setName($name)
                ->setSlug($slug)
                ->setLanguage($language)
                ->setIsInterestRelated(true)
                ->setIsAutoGenerated(false)
                ->setIsActive(true)
                ->setNotes('Tag de demonstration '.$media->value));
        }

        $report = (new SynthesisReport())
            ->setTitle('Synthese demo')
            ->setFromDate(new \DateTimeImmutable('-7 days'))
            ->setToDate(new \DateTimeImmutable())
            ->setNotes('Rapport de demonstration.')
            ->setGeneratedContent('Synthese de demonstration locale.');
        $report->addEntry($entries[0]);
        $report->addEntry($entries[2]);
        $report->addReview($review);
        $manager->persist($report);

        $manager->flush();
    }
}
