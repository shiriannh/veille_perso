<?php

namespace App\Entity;

use App\Repository\SynthesisReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SynthesisReportRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SynthesisReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $fromDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $toDate;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 30)]
    private string $format = 'html';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $generatedContent = null;

    #[ORM\Column]
    private bool $includeMaybeRelevant = false;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $criteria = null;

    /**
     * @var Collection<int, Entry>
     */
    #[ORM\ManyToMany(targetEntity: Entry::class, inversedBy: 'synthesisReports')]
    #[ORM\JoinTable(name: 'synthesis_report_entry')]
    private Collection $entries;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\ManyToMany(targetEntity: Review::class)]
    #[ORM\JoinTable(name: 'synthesis_report_review')]
    private Collection $reviews;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->toDate = $now;
        $this->title = 'Synthese du '.$now->format('d/m/Y H:i');
        $this->entries = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFromDate(): ?\DateTimeImmutable
    {
        return $this->fromDate;
    }

    public function setFromDate(?\DateTimeImmutable $fromDate): self
    {
        $this->fromDate = $fromDate;

        return $this;
    }

    public function getToDate(): \DateTimeImmutable
    {
        return $this->toDate;
    }

    public function setToDate(\DateTimeImmutable $toDate): self
    {
        $this->toDate = $toDate;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getGeneratedContent(): ?string
    {
        return $this->generatedContent;
    }

    public function setGeneratedContent(?string $generatedContent): self
    {
        $this->generatedContent = $generatedContent;

        return $this;
    }

    public function includeMaybeRelevant(): bool
    {
        return $this->includeMaybeRelevant;
    }

    public function setIncludeMaybeRelevant(bool $includeMaybeRelevant): self
    {
        $this->includeMaybeRelevant = $includeMaybeRelevant;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    /**
     * @param array<string, mixed>|null $criteria
     */
    public function setCriteria(?array $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(Entry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
