<?php

namespace App\Entity;

use App\Repository\SwTextVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite des variantes textuelles Symbolic Weather.
 * Pourquoi: isoler les contenus editoriaux exploitables par phase/version avec suivi de validation et de filiation.
 * Info: family=symbolic et reading_mode=weather sont imposes cote serveur pour rester coherents.
 */
#[ORM\Entity(repositoryClass: SwTextVariantRepository::class)]
#[ORM\Table(name: 'sw_text_variant')]
#[ORM\Index(name: 'idx_sw_text_variant_validated_used', columns: ['is_validated', 'is_used'])]
#[ORM\HasLifecycleCallbacks]
class SwTextVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $family = 'symbolic';

    #[ORM\Column(name: 'reading_mode', type: Types::STRING, length: 50)]
    private string $readingMode = 'weather';

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $lang = 'fr';

    #[ORM\Column(name: 'phase_key', type: Types::SMALLINT)]
    private int $phaseKey = 0;

    #[ORM\Column(name: 'variant_no', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $variantNo = 1;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: 'card_text', type: Types::TEXT)]
    private string $cardText = '';

    #[ORM\Column(name: 'full_text', type: Types::TEXT, nullable: true)]
    private ?string $fullText = null;

    #[ORM\Column(name: 'text_version', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $textVersion = 1;

    #[ORM\Column(name: 'is_validated', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isValidated = false;

    #[ORM\Column(name: 'is_used', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isUsed = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'editorial_notes', type: Types::TEXT, nullable: true)]
    private ?string $editorialNotes = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'rewrites')]
    #[ORM\JoinColumn(name: 'source_variant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceVariant = null;

    /**
     * @var Collection<int, SwTextVariant>
     */
    #[ORM\OneToMany(mappedBy: 'sourceVariant', targetEntity: self::class)]
    private Collection $rewrites;

    #[ORM\Column(name: 'created_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(name: 'updated_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAtUtc;

    public function __construct()
    {
        $now = self::nowUtc();
        $this->createdAtUtc = $now;
        $this->updatedAtUtc = $now;
        $this->rewrites = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function ensureTimestampsUtc(): void
    {
        if (!isset($this->createdAtUtc)) {
            $this->createdAtUtc = self::nowUtc();
        }
        if (!isset($this->updatedAtUtc)) {
            $this->updatedAtUtc = self::nowUtc();
        }
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAtUtc(): void
    {
        $this->updatedAtUtc = self::nowUtc();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFamily(): string
    {
        return $this->family;
    }

    public function setFamily(string $family): self
    {
        $this->family = $family;

        return $this;
    }

    public function getReadingMode(): string
    {
        return $this->readingMode;
    }

    public function setReadingMode(string $readingMode): self
    {
        $this->readingMode = $readingMode;

        return $this;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getPhaseKey(): int
    {
        return $this->phaseKey;
    }

    public function setPhaseKey(int $phaseKey): self
    {
        $this->phaseKey = $phaseKey;

        return $this;
    }

    public function getVariantNo(): int
    {
        return $this->variantNo;
    }

    public function setVariantNo(int $variantNo): self
    {
        $this->variantNo = $variantNo;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCardText(): string
    {
        return $this->cardText;
    }

    public function setCardText(string $cardText): self
    {
        $this->cardText = $cardText;

        return $this;
    }

    public function getFullText(): ?string
    {
        return $this->fullText;
    }

    public function setFullText(?string $fullText): self
    {
        $this->fullText = $fullText;

        return $this;
    }

    public function getTextVersion(): int
    {
        return $this->textVersion;
    }

    public function setTextVersion(int $textVersion): self
    {
        $this->textVersion = $textVersion;

        return $this;
    }

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): self
    {
        $this->isValidated = $isValidated;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): self
    {
        $this->isUsed = $isUsed;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getEditorialNotes(): ?string
    {
        return $this->editorialNotes;
    }

    public function setEditorialNotes(?string $editorialNotes): self
    {
        $this->editorialNotes = $editorialNotes;

        return $this;
    }

    public function getSourceVariant(): ?self
    {
        return $this->sourceVariant;
    }

    public function setSourceVariant(?self $sourceVariant): self
    {
        $this->sourceVariant = $sourceVariant;

        return $this;
    }

    /**
     * @return Collection<int, SwTextVariant>
     */
    public function getRewrites(): Collection
    {
        return $this->rewrites;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getUpdatedAtUtc(): \DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }

    private static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

