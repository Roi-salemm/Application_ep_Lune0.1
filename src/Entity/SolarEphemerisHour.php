<?php

namespace App\Entity;

use App\Repository\SolarEphemerisHourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SolarEphemerisHourRepository::class)]
#[ORM\Table(name: 'solar_ephemeris_hour', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_solar_ephemeris_hour_ts', columns: ['ts_utc']),
])]
class SolarEphemerisHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $ts_utc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $ra_hours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $dec_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $elon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $elat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 14, nullable: true)]
    private ?string $dist_au = null;

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

    public function setTsUtc(?\DateTime $ts_utc): static
    {
        $this->ts_utc = $ts_utc;

        return $this;
    }

    public function getRaHours(): ?string
    {
        return $this->ra_hours;
    }

    public function setRaHours(?string $ra_hours): static
    {
        $this->ra_hours = $ra_hours;

        return $this;
    }

    public function getDecDeg(): ?string
    {
        return $this->dec_deg;
    }

    public function setDecDeg(?string $dec_deg): static
    {
        $this->dec_deg = $dec_deg;

        return $this;
    }

    public function getElonDeg(): ?string
    {
        return $this->elon_deg;
    }

    public function setElonDeg(?string $elon_deg): static
    {
        $this->elon_deg = $elon_deg;

        return $this;
    }

    public function getElatDeg(): ?string
    {
        return $this->elat_deg;
    }

    public function setElatDeg(?string $elat_deg): static
    {
        $this->elat_deg = $elat_deg;

        return $this;
    }

    public function getDistAu(): ?string
    {
        return $this->dist_au;
    }

    public function setDistAu(?string $dist_au): static
    {
        $this->dist_au = $dist_au;

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
