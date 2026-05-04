<?php

namespace App\Entity;

use App\Repository\AnalysisCorrectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalysisCorrectionRepository::class)]
#[ORM\Index(columns: ['entry_id'], name: 'IDX_ANALYSIS_CORRECTION_ENTRY')]
#[ORM\Index(columns: ['field_name'], name: 'IDX_ANALYSIS_CORRECTION_FIELD')]
#[ORM\HasLifecycleCallbacks]
class AnalysisCorrection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Entry $entry = null;

    #[ORM\Column(length: 40)]
    private string $fieldName = '';

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValue = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

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

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOldValue(): ?array
    {
        return $this->oldValue;
    }

    /**
     * @param array<string, mixed>|null $oldValue
     */
    public function setOldValue(?array $oldValue): self
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNewValue(): ?array
    {
        return $this->newValue;
    }

    /**
     * @param array<string, mixed>|null $newValue
     */
    public function setNewValue(?array $newValue): self
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
