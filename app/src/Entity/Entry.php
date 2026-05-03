<?php

namespace App\Entity;

use App\Enum\EntryStatus;
use App\Enum\MediaType;
use App\Repository\EntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Source $source = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(enumType: MediaType::class)]
    private MediaType $mediaType = MediaType::VideoGame;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $authorOrStudio = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $originalUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $externalId = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawContent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawPayload = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $spottedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $importedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceHash = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 5)]
    private int $interestLevel = 0;

    #[ORM\Column(enumType: EntryStatus::class)]
    private EntryStatus $status = EntryStatus::ToWatch;

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $personalTags = [];

    #[ORM\OneToOne(mappedBy: 'entry', targetEntity: Review::class, cascade: ['persist', 'remove'])]
    private ?Review $review = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->spottedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): self
    {
        $this->source = $source;

        return $this;
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

    public function getMediaType(): MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): self
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getAuthorOrStudio(): ?string
    {
        return $this->authorOrStudio;
    }

    public function setAuthorOrStudio(?string $authorOrStudio): self
    {
        $this->authorOrStudio = $authorOrStudio;

        return $this;
    }

    public function getOriginalUrl(): ?string
    {
        return $this->originalUrl;
    }

    public function setOriginalUrl(?string $originalUrl): self
    {
        $this->originalUrl = $originalUrl;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }

    public function setRawContent(?string $rawContent): self
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    public function getSpottedAt(): \DateTimeImmutable
    {
        return $this->spottedAt;
    }

    public function setSpottedAt(\DateTimeImmutable $spottedAt): self
    {
        $this->spottedAt = $spottedAt;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getImportedAt(): ?\DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(?\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;

        return $this;
    }

    public function getSourceHash(): ?string
    {
        return $this->sourceHash;
    }

    public function setSourceHash(?string $sourceHash): self
    {
        $this->sourceHash = $sourceHash;

        return $this;
    }

    public function getInterestLevel(): int
    {
        return $this->interestLevel;
    }

    public function setInterestLevel(int $interestLevel): self
    {
        $this->interestLevel = $interestLevel;

        return $this;
    }

    public function getStatus(): EntryStatus
    {
        return $this->status;
    }

    public function setStatus(EntryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getPersonalTags(): array
    {
        return $this->personalTags;
    }

    /**
     * @param array<int, string> $personalTags
     */
    public function setPersonalTags(array $personalTags): self
    {
        $this->personalTags = array_values(array_filter(array_map('trim', $personalTags)));

        return $this;
    }

    public function getPersonalTagsAsString(): string
    {
        return implode(', ', $this->personalTags);
    }

    public function setPersonalTagsFromString(?string $personalTags): self
    {
        if ($personalTags === null || trim($personalTags) === '') {
            $this->personalTags = [];

            return $this;
        }

        $this->setPersonalTags(explode(',', $personalTags));

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): self
    {
        if ($review === null && $this->review !== null) {
            $this->review->setEntry(null);
        }

        if ($review !== null && $review->getEntry() !== $this) {
            $review->setEntry($this);
        }

        $this->review = $review;

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
