<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\Review;
use App\Enum\ReviewVerdict;
use Doctrine\ORM\EntityManagerInterface;

class DraftReviewCreator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createIfNeeded(Entry $entry): ?Review
    {
        if ($entry->getInterestLevel() < 4 || $entry->getReview() !== null) {
            return null;
        }

        $review = (new Review())
            ->setEntry($entry)
            ->setVerdict(ReviewVerdict::Curious)
            ->setScore($entry->getRelevanceScore())
            ->setIsDraft(true)
            ->setIsAutoCreated(true)
            ->setSummary($this->buildSummary($entry))
            ->setStrengths($this->buildStrengths($entry))
            ->setWeaknesses($this->buildWeaknesses($entry))
            ->setPersonalNote('Fiche brouillon creee automatiquement car le niveau d interet vaut '.$entry->getInterestLevel().'/5.');

        $this->entityManager->persist($review);
        $entry->setReview($review);

        return $review;
    }

    private function buildSummary(Entry $entry): string
    {
        $parts = [$entry->getTitle()];

        if ($entry->getSource() !== null) {
            $parts[] = 'Source : '.$entry->getSource()->getName();
        }

        if ($entry->getOriginalUrl() !== null) {
            $parts[] = 'Lien : '.$entry->getOriginalUrl();
        }

        if ($entry->getRawContent() !== null) {
            $parts[] = mb_substr(trim(strip_tags($entry->getRawContent())), 0, 600);
        }

        return implode("\n\n", array_filter($parts));
    }

    private function buildStrengths(Entry $entry): ?string
    {
        $matches = array_slice($entry->getMatchedPositiveKeywords(), 0, 10);

        return $matches === [] ? null : 'Signaux positifs : '.implode(', ', $matches).'.';
    }

    private function buildWeaknesses(Entry $entry): ?string
    {
        $signals = array_slice(array_merge($entry->getMatchedNegativeKeywords(), $entry->getClickbaitSignals()), 0, 10);

        return $signals === [] ? null : 'Points a verifier : '.implode(', ', $signals).'.';
    }
}
