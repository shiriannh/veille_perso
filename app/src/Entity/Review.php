<?php

namespace App\Entity;

use App\Enum\ReviewVerdict;
use App\Enum\ReviewNextAction;
use App\Enum\ReviewStatus;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'review')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entry $entry = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strengths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $weaknesses = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $personalNote = null;

    #[ORM\Column(enumType: ReviewVerdict::class)]
    private ReviewVerdict $verdict = ReviewVerdict::Curious;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $score = null;

    #[ORM\Column(enumType: ReviewStatus::class)]
    private ReviewStatus $status = ReviewStatus::ToComplete;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decisionAt = null;

    #[ORM\Column(enumType: ReviewNextAction::class, nullable: true)]
    private ?ReviewNextAction $nextAction = null;

    #[ORM\Column]
    private bool $isDraft = false;

    #[ORM\Column]
    private bool $isAutoCreated = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __toString(): string
    {
        return $this->entry?->getTitle() ?? 'Review';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): self
    {
        $this->entry = $entry;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getStrengths(): ?string
    {
        return $this->strengths;
    }

    public function setStrengths(?string $strengths): self
    {
        $this->strengths = $strengths;

        return $this;
    }

    public function getWeaknesses(): ?string
    {
        return $this->weaknesses;
    }

    public function setWeaknesses(?string $weaknesses): self
    {
        $this->weaknesses = $weaknesses;

        return $this;
    }

    public function getPersonalNote(): ?string
    {
        return $this->personalNote;
    }

    public function setPersonalNote(?string $personalNote): self
    {
        $this->personalNote = $personalNote;

        return $this;
    }

    public function getVerdict(): ReviewVerdict
    {
        return $this->verdict;
    }

    public function setVerdict(ReviewVerdict $verdict): self
    {
        $this->verdict = $verdict;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getStatus(): ReviewStatus
    {
        return $this->status;
    }

    public function setStatus(ReviewStatus $status): self
    {
        $this->status = $status;
        $this->isDraft = $status === ReviewStatus::Draft;

        return $this;
    }

    public function getDecisionAt(): ?\DateTimeImmutable
    {
        return $this->decisionAt;
    }

    public function setDecisionAt(?\DateTimeImmutable $decisionAt): self
    {
        $this->decisionAt = $decisionAt;

        return $this;
    }

    public function getNextAction(): ?ReviewNextAction
    {
        return $this->nextAction;
    }

    public function setNextAction(?ReviewNextAction $nextAction): self
    {
        $this->nextAction = $nextAction;

        return $this;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function setIsDraft(bool $isDraft): self
    {
        $this->isDraft = $isDraft;
        $this->status = $isDraft ? ReviewStatus::Draft : ReviewStatus::ToComplete;

        return $this;
    }

    public function isAutoCreated(): bool
    {
        return $this->isAutoCreated;
    }

    public function setIsAutoCreated(bool $isAutoCreated): self
    {
        $this->isAutoCreated = $isAutoCreated;

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
