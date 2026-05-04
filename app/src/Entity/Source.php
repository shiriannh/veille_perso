<?php

namespace App\Entity;

use App\Enum\FetchMode;
use App\Enum\SourceType;
use App\Repository\SourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(enumType: SourceType::class)]
    private SourceType $type = SourceType::Website;

    #[ORM\ManyToOne]
    private ?SourceTypeReference $sourceTypeReference = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(enumType: FetchMode::class)]
    private FetchMode $fetchMode = FetchMode::Manual;

    #[ORM\ManyToOne]
    private ?FetchModeReference $fetchModeReference = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private ?string $feedUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastFetchedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastErrorAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastErrorMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Entry>
     */
    #[ORM\OneToMany(mappedBy: 'source', targetEntity: Entry::class)]
    private Collection $entries;

    /**
     * @var Collection<int, ImportRun>
     */
    #[ORM\OneToMany(mappedBy: 'source', targetEntity: ImportRun::class, orphanRemoval: true)]
    private Collection $importRuns;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
        $this->importRuns = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): SourceType
    {
        return $this->type;
    }

    public function setType(SourceType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSourceTypeReference(): ?SourceTypeReference
    {
        return $this->sourceTypeReference;
    }

    public function setSourceTypeReference(?SourceTypeReference $sourceTypeReference): self
    {
        $this->sourceTypeReference = $sourceTypeReference;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
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

    public function getFetchMode(): FetchMode
    {
        return $this->fetchMode;
    }

    public function setFetchMode(FetchMode $fetchMode): self
    {
        $this->fetchMode = $fetchMode;

        return $this;
    }

    public function getFetchModeReference(): ?FetchModeReference
    {
        return $this->fetchModeReference;
    }

    public function setFetchModeReference(?FetchModeReference $fetchModeReference): self
    {
        $this->fetchModeReference = $fetchModeReference;

        return $this;
    }

    public function getFeedUrl(): ?string
    {
        return $this->feedUrl;
    }

    public function setFeedUrl(?string $feedUrl): self
    {
        $this->feedUrl = $feedUrl;

        return $this;
    }

    public function getLastFetchedAt(): ?\DateTimeImmutable
    {
        return $this->lastFetchedAt;
    }

    public function setLastFetchedAt(?\DateTimeImmutable $lastFetchedAt): self
    {
        $this->lastFetchedAt = $lastFetchedAt;

        return $this;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function setLastSuccessAt(?\DateTimeImmutable $lastSuccessAt): self
    {
        $this->lastSuccessAt = $lastSuccessAt;

        return $this;
    }

    public function getLastErrorAt(): ?\DateTimeImmutable
    {
        return $this->lastErrorAt;
    }

    public function setLastErrorAt(?\DateTimeImmutable $lastErrorAt): self
    {
        $this->lastErrorAt = $lastErrorAt;

        return $this;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): self
    {
        $this->lastErrorMessage = $lastErrorMessage;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    /**
     * @return Collection<int, ImportRun>
     */
    public function getImportRuns(): Collection
    {
        return $this->importRuns;
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
