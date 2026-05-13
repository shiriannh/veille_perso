<?php

namespace App\Tests\Service;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\MediaType;
use App\Service\ContentAdmissionPolicy;
use App\Service\ContentAdmissionResult;
use App\Service\EntryTagDetector;
use App\Service\MediaTypeResolver;
use App\Service\RssCategoryMapper;
use App\Service\Slugger;
use App\Service\TagGovernance;
use PHPUnit\Framework\TestCase;

class ContentAdmissionPolicyTest extends TestCase
{
    private ContentAdmissionPolicy $policy;

    protected function setUp(): void
    {
        $governance = new TagGovernance(new Slugger());
        $categoryMapper = new RssCategoryMapper($governance);

        $this->policy = new ContentAdmissionPolicy(
            new EntryTagDetector($governance),
            $categoryMapper,
            new MediaTypeResolver($categoryMapper),
            $governance,
        );
    }

    public function testItAdmitsBookWhenSourceProfileAndCategoriesMatchInterest(): void
    {
        $entry = $this->entry('Livres SFF', 'sff_books')
            ->setTitle('Nouveautes science-fiction et space opera')
            ->setRawContent('Un roman de science-fiction autour du transhumanisme.');

        $result = $this->policy->evaluate($entry, ['Romans VF', 'Space Opera', 'Transhumanisme']);

        self::assertSame(ContentAdmissionResult::ADMIT, $result->outcome);
        self::assertSame(MediaType::Book, $entry->getFinalMediaType());
        self::assertContains('space-opera', $entry->getDetectedTags());
        self::assertContains('transhumanisme', $entry->getDetectedTags());
    }

    public function testItRejectsNoisyGeneralistContentWithoutBusinessSignal(): void
    {
        $entry = $this->entry('Magazine generaliste', 'noisy_generalist')
            ->setTitle('Nouveau podcast festival blockbuster a telecharger')
            ->setRawContent('Actualites et nos conseils sans signal culturel cible.');

        $result = $this->policy->evaluate($entry, ['Nouveau', 'Festival', 'Download']);

        self::assertSame(ContentAdmissionResult::REJECT, $result->outcome);
        self::assertSame([], $entry->getDetectedTags());
        self::assertContains('nouveau', $entry->getRawDetectedTerms());
    }

    public function testNeutralProfileStillAdmitsStrongMediaAndPivotSignal(): void
    {
        $entry = $this->entry('Source neutre', 'none')
            ->setTitle('Roman de science-fiction space opera')
            ->setRawContent('Une nouveaute SFF avec un vrai signal space opera.');

        $result = $this->policy->evaluate($entry, ['Romans VF', 'Space Opera']);

        self::assertSame(ContentAdmissionResult::ADMIT, $result->outcome);
    }

    private function entry(string $sourceName, string $sourceProfile): Entry
    {
        $source = (new Source())
            ->setName($sourceName)
            ->setSourceProfile($sourceProfile);

        return (new Entry())
            ->setSource($source)
            ->setMediaType(MediaType::Other)
            ->setOriginalUrl('https://example.org/item');
    }
}
