<?php

namespace App\Entity;

use App\Repository\SwSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Projection denormalisee pour l envoi rapide au front.
 * Pourquoi: centraliser en une ligne les donnees utiles a l affichage carte + ouverture detail, sans jointures lourdes.
 * Info: une ligne snapshot represente un texte (1 sw_schedule), et suit l etat publie via is_active.
 */
#[ORM\Entity(repositoryClass: SwSnapshotRepository::class)]
#[ORM\Table(name: 'sw_snapshot')]
#[ORM\UniqueConstraint(name: 'uq_sw_snapshot_sw_schedule', columns: ['sw_schedule_id'])]
#[ORM\Index(name: 'idx_sw_snapshot_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_sw_snapshot_period', columns: ['starts_at', 'ends_at'])]
#[ORM\Index(name: 'idx_sw_snapshot_family_mode', columns: ['family', 'reading_mode', 'lang'])]
#[ORM\Index(name: 'idx_sw_snapshot_sw_display', columns: ['sw_display_id'])]
#[ORM\Index(name: 'idx_sw_snapshot_sw_content', columns: ['sw_content_id'])]
#[ORM\HasLifecycleCallbacks]
class SwSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: SwDisplay::class)]
    #[ORM\JoinColumn(name: 'sw_display_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwDisplay $swDisplay = null;

    #[ORM\ManyToOne(targetEntity: SwContent::class)]
    #[ORM\JoinColumn(name: 'sw_content_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwContent $swContent = null;

    #[ORM\ManyToOne(targetEntity: SwSchedule::class)]
    #[ORM\JoinColumn(name: 'sw_schedule_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwSchedule $swSchedule = null;

    #[ORM\Column(name: 'lang', type: Types::STRING, length: 10)]
    private string $lang;

    #[ORM\Column(name: 'family', type: Types::STRING, length: 50)]
    private string $family;

    #[ORM\Column(name: 'reading_mode', type: Types::STRING, length: 50)]
    private string $readingMode;

    #[ORM\Column(name: 'card_title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $cardTitle = null;

    #[ORM\Column(name: 'card_text', type: Types::TEXT)]
    private string $cardText = '';

    #[ORM\Column(name: 'content_json', type: Types::JSON)]
    private array $contentJson = [];

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = self::nowUtc();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->startsAt = $now;
        $this->endsAt = $now;
        $this->lang = 'fr';
        $this->family = 'symbolic';
        $this->readingMode = 'SYM_Influence';
    }

    #[ORM\PrePersist]
    public function ensureTimestampsUtc(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = self::nowUtc();
        }
        if (!isset($this->updatedAt)) {
            $this->updatedAt = self::nowUtc();
        }
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = self::nowUtc();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSwDisplay(): ?SwDisplay
    {
        return $this->swDisplay;
    }

    public function setSwDisplay(?SwDisplay $swDisplay): self
    {
        $this->swDisplay = $swDisplay;

        return $this;
    }

    public function getSwContent(): ?SwContent
    {
        return $this->swContent;
    }

    public function setSwContent(?SwContent $swContent): self
    {
        $this->swContent = $swContent;

        return $this;
    }

    public function getSwSchedule(): ?SwSchedule
    {
        return $this->swSchedule;
    }

    public function setSwSchedule(?SwSchedule $swSchedule): self
    {
        $this->swSchedule = $swSchedule;

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

    public function getCardTitle(): ?string
    {
        return $this->cardTitle;
    }

    public function setCardTitle(?string $cardTitle): self
    {
        $this->cardTitle = $cardTitle;

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

    public function getContentJson(): array
    {
        return $this->contentJson;
    }

    public function setContentJson(array $contentJson): self
    {
        $this->contentJson = $contentJson;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = self::normalizeUtc($startsAt);

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = self::normalizeUtc($endsAt);

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
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

