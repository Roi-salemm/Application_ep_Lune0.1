<?php

namespace App\Entity;

use App\Repository\OrbWindowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fenetre temporelle calculee autour d un evenement astronomique.
 * Pourquoi: stocker la reference exacte event_at_utc et les bornes de diffusion calculees en UTC.
 */
#[ORM\Entity(repositoryClass: OrbWindowRepository::class)]
#[ORM\Table(name: 'orb_window')]
#[ORM\Index(name: 'idx_orb_window_family', columns: ['window_family'])]
#[ORM\Index(name: 'idx_orb_window_phase_key', columns: ['phase_key'])]
#[ORM\Index(name: 'idx_orb_window_event_at', columns: ['event_at_utc'])]
#[ORM\Index(name: 'idx_orb_window_calc_method', columns: ['calculation_method'])]
#[ORM\Index(name: 'idx_orb_window_family_method_event', columns: ['window_family', 'calculation_method', 'event_at_utc'])]
#[ORM\Index(name: 'idx_orb_window_window', columns: ['starts_at_utc', 'ends_at_utc'])]
#[ORM\Index(name: 'idx_orb_window_phase_family_event', columns: ['phase_key', 'window_family', 'event_at_utc'])]
#[ORM\Index(name: 'idx_orb_window_lunation_family_seq', columns: ['lunation_key', 'window_family', 'sequence_no'])]
#[ORM\HasLifecycleCallbacks]
class OrbWindow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(name: 'window_family', type: Types::STRING, length: 50)]
    private string $windowFamily;

    #[ORM\Column(name: 'phase_key', type: Types::STRING, length: 50)]
    private string $phaseKey;

    #[ORM\Column(name: 'event_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $eventAtUtc;

    #[ORM\Column(name: 'starts_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAtUtc;

    #[ORM\Column(name: 'ends_at_utc', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $endsAtUtc;

    #[ORM\Column(name: 'lunation_key', type: Types::STRING, length: 50, nullable: true)]
    private ?string $lunationKey = null;

    #[ORM\Column(name: 'sequence_no', type: Types::INTEGER, nullable: true)]
    private ?int $sequenceNo = null;

    #[ORM\Column(name: 'calculation_method', type: Types::STRING, length: 100)]
    private string $calculationMethod;

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

    public function getWindowFamily(): string
    {
        return $this->windowFamily;
    }

    public function setWindowFamily(string $windowFamily): self
    {
        $this->windowFamily = $windowFamily;

        return $this;
    }

    public function getPhaseKey(): string
    {
        return $this->phaseKey;
    }

    public function setPhaseKey(string $phaseKey): self
    {
        $this->phaseKey = $phaseKey;

        return $this;
    }

    public function getEventAtUtc(): \DateTimeImmutable
    {
        return $this->eventAtUtc;
    }

    public function setEventAtUtc(\DateTimeImmutable $eventAtUtc): self
    {
        $this->eventAtUtc = self::normalizeUtc($eventAtUtc);

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

    public function getLunationKey(): ?string
    {
        return $this->lunationKey;
    }

    public function setLunationKey(?string $lunationKey): self
    {
        $this->lunationKey = $lunationKey;

        return $this;
    }

    public function getSequenceNo(): ?int
    {
        return $this->sequenceNo;
    }

    public function setSequenceNo(?int $sequenceNo): self
    {
        $this->sequenceNo = $sequenceNo;

        return $this;
    }

    public function getCalculationMethod(): string
    {
        return $this->calculationMethod;
    }

    public function setCalculationMethod(string $calculationMethod): self
    {
        $this->calculationMethod = $calculationMethod;

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
