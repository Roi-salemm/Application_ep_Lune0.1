<?php

namespace App\Entity;

use App\Repository\SwScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite de diffusion temporelle qui lie un affichage a une version de contenu.
 * Pourquoi: publier en UTC une version precise selon une fenetre, une priorite et un statut de publication.
 * Info: les colonnes display_id et content_id restent en base et sont mappees en ManyToOne via JoinColumn.
 */
#[ORM\Entity(repositoryClass: SwScheduleRepository::class)]
#[ORM\Table(name: 'sw_schedule')]
#[ORM\Index(name: 'idx_sw_schedule_window', columns: ['starts_at_utc', 'ends_at_utc'])]
#[ORM\Index(name: 'idx_sw_schedule_display_published', columns: ['display_id', 'is_published'])]
#[ORM\Index(name: 'idx_sw_schedule_content', columns: ['content_id'])]
#[ORM\Index(name: 'idx_sw_schedule_priority', columns: ['priority'])]
#[ORM\HasLifecycleCallbacks]
class SwSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: SwDisplay::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(name: 'display_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwDisplay $display = null;

    #[ORM\ManyToOne(targetEntity: SwContent::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(name: 'content_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SwContent $content = null;

    #[ORM\Column(name: 'schedule_type', type: Types::STRING, length: 30, columnDefinition: "ENUM('phase_window','influence_window')")]
    private string $scheduleType;

    #[ORM\Column(name: 'starts_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAtUtc;

    #[ORM\Column(name: 'ends_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $endsAtUtc;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $priority = 0;

    #[ORM\Column(name: 'is_published', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'payload_json', type: Types::JSON, nullable: true)]
    private ?array $payloadJson = null;

    #[ORM\Column(name: 'created_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(name: 'updated_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAtUtc;

    public function __construct()
    {
        $now = self::nowUtc();
        $this->createdAtUtc = $now;
        $this->updatedAtUtc = $now;
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

    public function getContent(): ?SwContent
    {
        return $this->content;
    }

    public function setContent(?SwContent $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getScheduleType(): string
    {
        return $this->scheduleType;
    }

    public function setScheduleType(string $scheduleType): self
    {
        $this->scheduleType = $scheduleType;

        return $this;
    }

    public function getStartsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc;
    }

    public function setStartsAtUtc(\DateTimeImmutable $startsAtUtc): self
    {
        $this->startsAtUtc = self::normalizeUtc($startsAtUtc);

        return $this;
    }

    public function getEndsAtUtc(): \DateTimeImmutable
    {
        return $this->endsAtUtc;
    }

    public function setEndsAtUtc(\DateTimeImmutable $endsAtUtc): self
    {
        $this->endsAtUtc = self::normalizeUtc($endsAtUtc);

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

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

    public function getPayloadJson(): ?array
    {
        return $this->payloadJson;
    }

    public function setPayloadJson(?array $payloadJson): self
    {
        $this->payloadJson = $payloadJson;

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

    private static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function normalizeUtc(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }
}
