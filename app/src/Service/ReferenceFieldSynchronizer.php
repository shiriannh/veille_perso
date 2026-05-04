<?php

namespace App\Service;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\Entry;
use App\Entity\FetchModeReference;
use App\Entity\MediaTypeReference;
use App\Entity\ReferenceEntityInterface;
use App\Entity\Source;
use App\Entity\SourceTypeReference;
use App\Enum\ClickbaitLevel;
use App\Enum\FetchMode;
use App\Enum\MediaType;
use App\Enum\SourceType;
use Doctrine\ORM\EntityManagerInterface;

class ReferenceFieldSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function syncEntryToReferences(Entry $entry): void
    {
        $entry
            ->setMediaTypeReference($this->findReference(MediaTypeReference::class, $entry->getMediaType()->value))
            ->setDecisionTypeReference($entry->getDecision() !== null ? $this->findReference(DecisionTypeReference::class, $entry->getDecision()->value) : null)
            ->setClickbaitLevelReference($entry->getClickbaitLevel() instanceof ClickbaitLevel ? $this->findReference(ClickbaitLevelReference::class, $entry->getClickbaitLevel()->value) : null)
            ->setAnalysisLanguageReference($entry->getAnalysisLanguage() !== null ? $this->findReference(AnalysisLanguageReference::class, $entry->getAnalysisLanguage()) : null);
    }

    public function syncEntryFromReferences(Entry $entry): void
    {
        $mediaTypeReference = $entry->getMediaTypeReference();
        if ($mediaTypeReference instanceof MediaTypeReference) {
            $mediaType = MediaType::tryFrom($mediaTypeReference->getSlug());
            if ($mediaType instanceof MediaType) {
                $entry->setMediaType($mediaType);
            }
        }
    }

    public function syncSourceToReferences(Source $source): void
    {
        $source
            ->setSourceTypeReference($this->findReference(SourceTypeReference::class, $source->getType()->value))
            ->setFetchModeReference($this->findReference(FetchModeReference::class, $source->getFetchMode()->value));
    }

    public function syncSourceFromReferences(Source $source): void
    {
        $sourceTypeReference = $source->getSourceTypeReference();
        if ($sourceTypeReference instanceof SourceTypeReference) {
            $sourceType = SourceType::tryFrom($sourceTypeReference->getSlug());
            if ($sourceType instanceof SourceType) {
                $source->setType($sourceType);
            }
        }

        $fetchModeReference = $source->getFetchModeReference();
        if ($fetchModeReference instanceof FetchModeReference) {
            $fetchMode = FetchMode::tryFrom($fetchModeReference->getSlug());
            if ($fetchMode instanceof FetchMode) {
                $source->setFetchMode($fetchMode);
            }
        }
    }

    /**
     * @template T of ReferenceEntityInterface
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    private function findReference(string $className, string $slug): ?ReferenceEntityInterface
    {
        $reference = $this->entityManager->getRepository($className)->findOneBy(['slug' => $slug]);

        return $reference instanceof ReferenceEntityInterface ? $reference : null;
    }
}
