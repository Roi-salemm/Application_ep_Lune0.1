<?php

/**
 * Entite Doctrine pour la table ms_mapping.
 * Pourquoi: typer le mapping journalier en PHP avec l ORM.
 * Infos: les champs numeriques restent en string pour preserver la precision.
 */

namespace App\Entity;

use App\Repository\MsMappingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MsMappingRepository::class)]
#[ORM\Table(name: 'ms_mapping')]
class MsMapping
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'ts_utc')]
    private ?\DateTimeImmutable $ts_utc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 10, nullable: true, name: 'm43_pab_lon_deg')]
    private ?string $m43_pab_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true, name: 'm10_illum_frac')]
    private ?string $m10_illum_frac = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 10, nullable: true, name: 'm31_ecl_lon_deg')]
    private ?string $m31_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 10, nullable: true, name: 's31_ecl_lon_deg')]
    private ?string $s31_ecl_lon_deg = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, name: 'phase')]
    private ?int $phase = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'phase_hour')]
    private ?\DateTimeImmutable $phase_hour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTsUtc(): ?\DateTimeImmutable
    {
        return $this->ts_utc;
    }

    public function setTsUtc(?\DateTimeImmutable $ts_utc): static
    {
        $this->ts_utc = $ts_utc;

        return $this;
    }

    public function getM43PabLonDeg(): ?string
    {
        return $this->m43_pab_lon_deg;
    }

    public function setM43PabLonDeg(?string $m43_pab_lon_deg): static
    {
        $this->m43_pab_lon_deg = $m43_pab_lon_deg;

        return $this;
    }

    public function getM10IllumFrac(): ?string
    {
        return $this->m10_illum_frac;
    }

    public function setM10IllumFrac(?string $m10_illum_frac): static
    {
        $this->m10_illum_frac = $m10_illum_frac;

        return $this;
    }

    public function getM31EclLonDeg(): ?string
    {
        return $this->m31_ecl_lon_deg;
    }

    public function setM31EclLonDeg(?string $m31_ecl_lon_deg): static
    {
        $this->m31_ecl_lon_deg = $m31_ecl_lon_deg;

        return $this;
    }

    public function getS31EclLonDeg(): ?string
    {
        return $this->s31_ecl_lon_deg;
    }

    public function setS31EclLonDeg(?string $s31_ecl_lon_deg): static
    {
        $this->s31_ecl_lon_deg = $s31_ecl_lon_deg;

        return $this;
    }

    public function getPhase(): ?int
    {
        return $this->phase;
    }

    public function setPhase(?int $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function getPhaseHour(): ?\DateTimeImmutable
    {
        return $this->phase_hour;
    }

    public function setPhaseHour(?\DateTimeImmutable $phase_hour): static
    {
        $this->phase_hour = $phase_hour;

        return $this;
    }
}
