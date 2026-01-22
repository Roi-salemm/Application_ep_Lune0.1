<?php

namespace App\Entity;

use App\Repository\MoonPhaseEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoonPhaseEventRepository::class)]
#[ORM\Table(name: 'moon_phase_event', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_moon_phase_event_type_ts', columns: ['event_type', 'ts_utc']),
])]
#[ORM\Index(name: 'idx_moon_phase_event_ts', columns: ['ts_utc'])]
#[ORM\Index(name: 'idx_moon_phase_event_type', columns: ['event_type'])]
class MoonPhaseEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $ts_utc = null;

    #[ORM\Column(length: 32)]
    private ?string $event_type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $phase_deg = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $precision_sec = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $source = null;

    #[ORM\Column]
    private ?\DateTime $created_at_utc = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTsUtc(): ?\DateTime
    {
        return $this->ts_utc;
    }

    public function setTsUtc(\DateTime $ts_utc): static
    {
        $this->ts_utc = $ts_utc;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->event_type;
    }

    public function setEventType(string $event_type): static
    {
        $this->event_type = $event_type;

        return $this;
    }

    public function getPhaseDeg(): ?string
    {
        return $this->phase_deg;
    }

    public function setPhaseDeg(?string $phase_deg): static
    {
        $this->phase_deg = $phase_deg;

        return $this;
    }

    public function getPrecisionSec(): ?int
    {
        return $this->precision_sec;
    }

    public function setPrecisionSec(?int $precision_sec): static
    {
        $this->precision_sec = $precision_sec;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getCreatedAtUtc(): ?\DateTime
    {
        return $this->created_at_utc;
    }

    public function setCreatedAtUtc(\DateTime $created_at_utc): static
    {
        $this->created_at_utc = $created_at_utc;

        return $this;
    }
}
