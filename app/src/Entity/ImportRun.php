<?php

namespace App\Entity;

use App\Enum\ImportRunStatus;
use App\Repository\ImportRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportRunRepository::class)]
class ImportRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'importRuns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Source $source = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(enumType: ImportRunStatus::class)]
    private ImportRunStatus $status = ImportRunStatus::Running;

    #[ORM\Column]
    private int $fetchedCount = 0;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $skippedCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
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

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getStatus(): ImportRunStatus
    {
        return $this->status;
    }

    public function setStatus(ImportRunStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFetchedCount(): int
    {
        return $this->fetchedCount;
    }

    public function setFetchedCount(int $fetchedCount): self
    {
        $this->fetchedCount = $fetchedCount;

        return $this;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function setCreatedCount(int $createdCount): self
    {
        $this->createdCount = $createdCount;

        return $this;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function setSkippedCount(int $skippedCount): self
    {
        $this->skippedCount = $skippedCount;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;

        return $this;
    }
}
