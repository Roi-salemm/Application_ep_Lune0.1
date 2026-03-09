<?php

namespace App\Entity;

use App\Repository\SwDisplayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite racine qui definit l identite metier d un affichage.
 * Pourquoi: separer la definition fonctionnelle (role, mode, activation) du contenu versionne et de sa diffusion.
 * Info: les dates sont forcees en UTC et les liens vers contenu/planning sont portes par les associations Doctrine.
 */
#[ORM\Entity(repositoryClass: SwDisplayRepository::class)]
#[ORM\Table(name: 'sw_display')]
#[ORM\UniqueConstraint(name: 'uq_sw_display_code', columns: ['code'])]
#[ORM\Index(name: 'idx_sw_display_family_mode', columns: ['family', 'reading_mode'])]
#[ORM\Index(name: 'idx_sw_display_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class SwDisplay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $family;

    #[ORM\Column(name: 'reading_mode', type: Types::STRING, length: 50)]
    private string $readingMode;

    #[ORM\Column(name: 'phase_key', type: Types::STRING, length: 50, nullable: true)]
    private ?string $phaseKey = null;

    #[ORM\Column(name: 'influence_key', type: Types::STRING, length: 50, nullable: true)]
    private ?string $influenceKey = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $lang;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(name: 'updated_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAtUtc;

    /**
     * @var Collection<int, SwContent>
     */
    #[ORM\OneToMany(mappedBy: 'display', targetEntity: SwContent::class)]
    private Collection $contents;

    /**
     * @var Collection<int, SwSchedule>
     */
    #[ORM\OneToMany(mappedBy: 'display', targetEntity: SwSchedule::class)]
    private Collection $schedules;

    public function __construct()
    {
        $now = self::nowUtc();
        $this->createdAtUtc = $now;
        $this->updatedAtUtc = $now;
        $this->contents = new ArrayCollection();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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

    public function getPhaseKey(): ?string
    {
        return $this->phaseKey;
    }

    public function setPhaseKey(?string $phaseKey): self
    {
        $this->phaseKey = $phaseKey;

        return $this;
    }

    public function getInfluenceKey(): ?string
    {
        return $this->influenceKey;
    }

    public function setInfluenceKey(?string $influenceKey): self
    {
        $this->influenceKey = $influenceKey;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

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

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getUpdatedAtUtc(): \DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }

    /**
     * @return Collection<int, SwContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function addContent(SwContent $content): self
    {
        if (!$this->contents->contains($content)) {
            $this->contents->add($content);
            $content->setDisplay($this);
        }

        return $this;
    }

    public function removeContent(SwContent $content): self
    {
        if ($this->contents->removeElement($content)) {
            if ($content->getDisplay() === $this) {
                $content->setDisplay(null);
            }
        }

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
            $schedule->setDisplay($this);
        }

        return $this;
    }

    public function removeSchedule(SwSchedule $schedule): self
    {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getDisplay() === $this) {
                $schedule->setDisplay(null);
            }
        }

        return $this;
    }

    private static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
