<?php

namespace App\Entity;

use App\Repository\SwContentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite qui porte la version editoriale reelle du contenu.
 * Pourquoi: conserver le JSON source, la validation humaine et la tracabilite des versions par affichage.
 * Info: la colonne display_id est mappee en association ManyToOne vers SwDisplay.
 */
#[ORM\Entity(repositoryClass: SwContentRepository::class)]
#[ORM\Table(name: 'sw_content')]
#[ORM\UniqueConstraint(name: 'uq_sw_content_display_version', columns: ['display_id', 'version_no'])]
#[ORM\Index(name: 'idx_sw_content_status', columns: ['status'])]
#[ORM\Index(name: 'idx_sw_content_current', columns: ['is_current'])]
#[ORM\Index(name: 'idx_sw_content_validated', columns: ['is_validated', 'validated_at_utc'])]
#[ORM\HasLifecycleCallbacks]
class SwContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: SwDisplay::class, inversedBy: 'contents')]
    #[ORM\JoinColumn(name: 'display_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwDisplay $display = null;

    #[ORM\Column(name: 'version_no', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $versionNo;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $status;

    #[ORM\Column(name: 'is_current', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isCurrent = false;

    #[ORM\Column(name: 'is_validated', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isValidated = false;

    #[ORM\Column(name: 'content_json', type: Types::JSON)]
    private array $contentJson = [];

    #[ORM\Column(name: 'schema_version', type: Types::STRING, length: 20)]
    private string $schemaVersion;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'editorial_notes', type: Types::TEXT, nullable: true)]
    private ?string $editorialNotes = null;

    #[ORM\Column(name: 'ai_model', type: Types::STRING, length: 120, nullable: true)]
    private ?string $aiModel = null;

    #[ORM\Column(name: 'ai_prompt_version', type: Types::STRING, length: 80, nullable: true)]
    private ?string $aiPromptVersion = null;

    #[ORM\Column(name: 'created_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(name: 'updated_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAtUtc;

    #[ORM\Column(name: 'validated_at_utc', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAtUtc = null;

    /**
     * @var Collection<int, SwSchedule>
     */
    #[ORM\OneToMany(mappedBy: 'content', targetEntity: SwSchedule::class)]
    private Collection $schedules;

    public function __construct()
    {
        $now = self::nowUtc();
        $this->createdAtUtc = $now;
        $this->updatedAtUtc = $now;
        $this->schedules = new ArrayCollection();
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

    public function getDisplay(): ?SwDisplay
    {
        return $this->display;
    }

    public function setDisplay(?SwDisplay $display): self
    {
        $this->display = $display;

        return $this;
    }

    public function getVersionNo(): int
    {
        return $this->versionNo;
    }

    public function setVersionNo(int $versionNo): self
    {
        $this->versionNo = $versionNo;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): self
    {
        $this->isCurrent = $isCurrent;

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

    public function getContentJson(): array
    {
        return $this->contentJson;
    }

    public function setContentJson(array $contentJson): self
    {
        $this->contentJson = $contentJson;

        return $this;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(string $schemaVersion): self
    {
        $this->schemaVersion = $schemaVersion;

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

    public function getAiModel(): ?string
    {
        return $this->aiModel;
    }

    public function setAiModel(?string $aiModel): self
    {
        $this->aiModel = $aiModel;

        return $this;
    }

    public function getAiPromptVersion(): ?string
    {
        return $this->aiPromptVersion;
    }

    public function setAiPromptVersion(?string $aiPromptVersion): self
    {
        $this->aiPromptVersion = $aiPromptVersion;

        return $this;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getUpdatedAtUtc(): \DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }

    public function getValidatedAtUtc(): ?\DateTimeImmutable
    {
        return $this->validatedAtUtc;
    }

    public function setValidatedAtUtc(?\DateTimeImmutable $validatedAtUtc): self
    {
        $this->validatedAtUtc = $validatedAtUtc === null ? null : self::normalizeUtc($validatedAtUtc);

        return $this;
    }

    /**
     * @return Collection<int, SwSchedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(SwSchedule $schedule): self
    {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setContent($this);
        }

        return $this;
    }

    public function removeSchedule(SwSchedule $schedule): self
    {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getContent() === $this) {
                $schedule->setContent(null);
            }
        }

        return $this;
    }

    private static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function normalizeUtc(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }
}
