<?php

namespace App\DataFixtures;

use App\Entity\Entry;
use App\Entity\Review;
use App\Entity\Source;
use App\Enum\EntryStatus;
use App\Enum\MediaType;
use App\Enum\ReviewVerdict;
use App\Enum\SourceType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $source = (new Source())
            ->setName('Canard PC')
            ->setType(SourceType::Website)
            ->setUrl('https://www.canardpc.com')
            ->setNotes('Source jeux vidéo à consulter sans urgence.');

        $entry = (new Entry())
            ->setSource($source)
            ->setTitle('Exemple de jeu tactique à surveiller')
            ->setMediaType(MediaType::VideoGame)
            ->setAuthorOrStudio('Studio indépendant')
            ->setOriginalUrl('https://example.com/jeu-tactique')
            ->setRawContent('Annonce repérée : jeu tactique narratif, sortie estimée cette année.')
            ->setInterestLevel(4)
            ->setStatus(EntryStatus::ToWatch)
            ->setPersonalTags(['tactique', 'indé', 'narratif']);

        $review = (new Review())
            ->setEntry($entry)
            ->setSummary('Un signal intéressant pour une veille jeux tactiques, à confirmer avec des previews plus détaillées.')
            ->setStrengths('Direction claire, promesse tactique lisible, ton narratif intrigant.')
            ->setWeaknesses('Peu de gameplay visible pour le moment.')
            ->setPersonalNote('À revoir quand une démo ou une vidéo longue sera disponible.')
            ->setVerdict(ReviewVerdict::Curious)
            ->setScore(72);

        $manager->persist($source);
        $manager->persist($entry);
        $manager->persist($review);
        $manager->flush();
    }
}
