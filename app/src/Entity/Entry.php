<?php

namespace App\Entity;

use App\Enum\AnalysisDecision;
use App\Enum\AnalysisStatus;
use App\Enum\ClickbaitLevel;
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $normalizedTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $normalizedContent = null;

    #[ORM\Column(nullable: true)]
    private ?int $relevanceScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $clickbaitScore = null;

    #[ORM\Column(enumType: AnalysisDecision::class, nullable: true)]
    private ?AnalysisDecision $decision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decisionReason = null;

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $matchedPositiveKeywords = [];

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $matchedNegativeKeywords = [];

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $clickbaitSignals = [];

    #[ORM\Column(enumType: ClickbaitLevel::class, nullable: true)]
    private ?ClickbaitLevel $clickbaitLevel = null;

    #[ORM\Column(nullable: true)]
    private ?int $mediaDetectionConfidence = null;

    #[ORM\Column(nullable: true)]
    private ?int $thematicScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $editorialQualityScore = null;

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $analysisSignals = [];

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $analysisLanguage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $analyzedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiAnalyzedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $aiModel = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiRawResult = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $analysisVersion = null;

    #[ORM\Column(enumType: AnalysisStatus::class)]
    private AnalysisStatus $analysisStatus = AnalysisStatus::Pending;

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

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $detectedTags = [];

    #[ORM\Column(enumType: MediaType::class, nullable: true)]
    private ?MediaType $detectedMediaType = null;

    #[ORM\OneToOne(mappedBy: 'entry', targetEntity: Review::class, cascade: ['persist'])]
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

    public function getNormalizedTitle(): ?string
    {
        return $this->normalizedTitle;
    }

    public function setNormalizedTitle(?string $normalizedTitle): self
    {
        $this->normalizedTitle = $normalizedTitle;

        return $this;
    }

    public function getNormalizedContent(): ?string
    {
        return $this->normalizedContent;
    }

    public function setNormalizedContent(?string $normalizedContent): self
    {
        $this->normalizedContent = $normalizedContent;

        return $this;
    }

    public function getRelevanceScore(): ?int
    {
        return $this->relevanceScore;
    }

    public function setRelevanceScore(?int $relevanceScore): self
    {
        $this->relevanceScore = $relevanceScore;

        return $this;
    }

    public function getClickbaitScore(): ?int
    {
        return $this->clickbaitScore;
    }

    public function setClickbaitScore(?int $clickbaitScore): self
    {
        $this->clickbaitScore = $clickbaitScore;

        return $this;
    }

    public function getDecision(): ?AnalysisDecision
    {
        return $this->decision;
    }

    public function setDecision(?AnalysisDecision $decision): self
    {
        $this->decision = $decision;

        return $this;
    }

    public function getDecisionReason(): ?string
    {
        return $this->decisionReason;
    }

    public function setDecisionReason(?string $decisionReason): self
    {
        $this->decisionReason = $decisionReason;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getMatchedPositiveKeywords(): array
    {
        return $this->matchedPositiveKeywords;
    }

    /**
     * @param array<int, string> $matchedPositiveKeywords
     */
    public function setMatchedPositiveKeywords(array $matchedPositiveKeywords): self
    {
        $this->matchedPositiveKeywords = array_values(array_unique($matchedPositiveKeywords));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getMatchedNegativeKeywords(): array
    {
        return $this->matchedNegativeKeywords;
    }

    /**
     * @param array<int, string> $matchedNegativeKeywords
     */
    public function setMatchedNegativeKeywords(array $matchedNegativeKeywords): self
    {
        $this->matchedNegativeKeywords = array_values(array_unique($matchedNegativeKeywords));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getClickbaitSignals(): array
    {
        return $this->clickbaitSignals;
    }

    /**
     * @param array<int, string> $clickbaitSignals
     */
    public function setClickbaitSignals(array $clickbaitSignals): self
    {
        $this->clickbaitSignals = array_values(array_unique($clickbaitSignals));

        return $this;
    }

    public function getClickbaitLevel(): ?ClickbaitLevel
    {
        return $this->clickbaitLevel;
    }

    public function setClickbaitLevel(?ClickbaitLevel $clickbaitLevel): self
    {
        $this->clickbaitLevel = $clickbaitLevel;

        return $this;
    }

    public function getMediaDetectionConfidence(): ?int
    {
        return $this->mediaDetectionConfidence;
    }

    public function setMediaDetectionConfidence(?int $mediaDetectionConfidence): self
    {
        $this->mediaDetectionConfidence = $mediaDetectionConfidence;

        return $this;
    }

    public function getThematicScore(): ?int
    {
        return $this->thematicScore;
    }

    public function setThematicScore(?int $thematicScore): self
    {
        $this->thematicScore = $thematicScore;

        return $this;
    }

    public function getEditorialQualityScore(): ?int
    {
        return $this->editorialQualityScore;
    }

    public function setEditorialQualityScore(?int $editorialQualityScore): self
    {
        $this->editorialQualityScore = $editorialQualityScore;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAnalysisSignals(): array
    {
        return $this->analysisSignals;
    }

    /**
     * @param array<int, string> $analysisSignals
     */
    public function setAnalysisSignals(array $analysisSignals): self
    {
        $this->analysisSignals = array_values(array_unique(array_filter(array_map('trim', $analysisSignals))));

        return $this;
    }

    public function getAnalysisLanguage(): ?string
    {
        return $this->analysisLanguage;
    }

    public function setAnalysisLanguage(?string $analysisLanguage): self
    {
        $this->analysisLanguage = $analysisLanguage;

        return $this;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(?\DateTimeImmutable $analyzedAt): self
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }

    public function getAiAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->aiAnalyzedAt;
    }

    public function setAiAnalyzedAt(?\DateTimeImmutable $aiAnalyzedAt): self
    {
        $this->aiAnalyzedAt = $aiAnalyzedAt;

        return $this;
    }

    public function getAiModel(): ?string
    {
        return $this->aiModel;
    }

    public function setAiModel(?string $aiModel): self
    {
        $this->aiModel = $aiModel;

        return $this;
    }

    public function getAiRawResult(): ?array
    {
        return $this->aiRawResult;
    }

    public function setAiRawResult(?array $aiRawResult): self
    {
        $this->aiRawResult = $aiRawResult;

        return $this;
    }

    public function getAnalysisVersion(): ?string
    {
        return $this->analysisVersion;
    }

    public function setAnalysisVersion(?string $analysisVersion): self
    {
        $this->analysisVersion = $analysisVersion;

        return $this;
    }

    public function getAnalysisStatus(): AnalysisStatus
    {
        return $this->analysisStatus;
    }

    public function setAnalysisStatus(AnalysisStatus $analysisStatus): self
    {
        $this->analysisStatus = $analysisStatus;

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

    /**
     * @return array<int, string>
     */
    public function getDetectedTags(): array
    {
        return $this->detectedTags;
    }

    /**
     * @param array<int, string> $detectedTags
     */
    public function setDetectedTags(array $detectedTags): self
    {
        $this->detectedTags = array_values(array_unique(array_filter(array_map('trim', $detectedTags))));

        return $this;
    }

    public function getDetectedMediaType(): ?MediaType
    {
        return $this->detectedMediaType;
    }

    public function setDetectedMediaType(?MediaType $detectedMediaType): self
    {
        $this->detectedMediaType = $detectedMediaType;

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
