<?php

namespace App\Service;

use App\Entity\AnalysisLanguageReference;
use App\Entity\ClickbaitLevelReference;
use App\Entity\DecisionTypeReference;
use App\Entity\FetchModeReference;
use App\Entity\MediaTypeReference;
use App\Entity\ReferenceEntityInterface;
use App\Entity\SourceTypeReference;
use App\Enum\AnalysisDecision;
use App\Enum\ClickbaitLevel;
use App\Enum\FetchMode;
use App\Enum\MediaType;
use App\Enum\SourceType;
use Doctrine\ORM\EntityManagerInterface;

class ReferenceDataSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function seed(bool $flush = true): void
    {
        $this->seedEnum(MediaTypeReference::class, MediaType::cases());
        $this->seedEnum(SourceTypeReference::class, SourceType::cases());
        $this->seedEnum(FetchModeReference::class, FetchMode::cases());
        $this->seedEnum(DecisionTypeReference::class, AnalysisDecision::cases());
        $this->seedEnum(ClickbaitLevelReference::class, ClickbaitLevel::cases());
        $this->seedStatic(AnalysisLanguageReference::class, [
            'fr' => 'Francais',
            'en' => 'Anglais',
            'mixed' => 'Mixte',
            'unknown' => 'Inconnue',
        ]);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param class-string<ReferenceEntityInterface> $className
     * @param array<int, object> $cases
     */
    private function seedEnum(string $className, array $cases): void
    {
        $values = [];
        foreach ($cases as $case) {
            if (!$case instanceof \BackedEnum || !method_exists($case, 'label')) {
                continue;
            }

            $values[$case->value] = $case->label();
        }

        $this->seedStatic($className, $values);
    }

    /**
     * @param class-string<ReferenceEntityInterface> $className
     * @param array<string, string> $values
     */
    private function seedStatic(string $className, array $values): void
    {
        $repository = $this->entityManager->getRepository($className);

        foreach ($values as $slug => $name) {
            if ($repository->findOneBy(['slug' => $slug]) instanceof ReferenceEntityInterface) {
                continue;
            }

            $reference = new $className();
            $reference
                ->setSlug($slug)
                ->setName($name)
                ->setIsActive(true);

            $this->entityManager->persist($reference);
        }
    }
}
