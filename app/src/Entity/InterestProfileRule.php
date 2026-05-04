<?php

namespace App\Entity;

use App\Repository\InterestProfileRuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterestProfileRuleRepository::class)]
#[ORM\Index(columns: ['category'], name: 'IDX_INTEREST_PROFILE_RULE_CATEGORY')]
#[ORM\HasLifecycleCallbacks]
class InterestProfileRule
{
    public const CATEGORIES = [
        'positive_keyword' => 'Mot-cle positif',
        'negative_keyword' => 'Mot-cle negatif',
        'boosted_phrase' => 'Expression favorisee',
        'excluded_phrase' => 'Expression ecartee',
        'preferred_media' => 'Media prefere',
        'license' => 'Licence / univers',
        'studio' => 'Studio / editeur',
        'author' => 'Auteur',
    ];

    public const WEIGHTS = [
        'low' => 'Faible',
        'normal' => 'Normal',
        'high' => 'Fort',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(callback: [self::class, 'categoryChoices'])]
    private string $category = 'positive_keyword';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $value = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(callback: [self::class, 'weightChoices'])]
    private string $weight = 'normal';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @return array<int, string>
     */
    public static function categoryChoices(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /**
     * @return array<int, string>
     */
    public static function weightChoices(): array
    {
        return array_keys(self::WEIGHTS);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = trim($value);

        return $this;
    }

    public function getWeight(): string
    {
        return $this->weight;
    }

    public function setWeight(string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function weightLabel(): string
    {
        return self::WEIGHTS[$this->weight] ?? $this->weight;
    }

    public function scoreWeight(): int
    {
        return match ($this->weight) {
            'low' => 4,
            'high' => 12,
            default => 7,
        };
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
