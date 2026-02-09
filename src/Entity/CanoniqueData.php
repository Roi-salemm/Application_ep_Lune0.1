<?php

/**
 * Entite Doctrine pour la table canonique_data.
 * Pourquoi: typer les donnees canoniques en PHP sans changer le schema.
 * Infos: les champs numeriques restent en string pour preserver la precision.
 */

namespace App\Entity;

use App\Repository\CanoniqueDataEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CanoniqueDataEntityRepository::class)]
#[ORM\Table(name: 'canonique_data')]
class CanoniqueData
{
    #[ORM\Id]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'ts_utc')]
    private ?\DateTimeImmutable $ts_utc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm1_ra_ast_deg')]
    private ?string $m1_ra_ast_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm1_dec_ast_deg')]
    private ?string $m1_dec_ast_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm2_ra_app_deg')]
    private ?string $m2_ra_app_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm2_dec_app_deg')]
    private ?string $m2_dec_app_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 14, nullable: true, name: 'm10_illum_frac')]
    private ?string $m10_illum_frac = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 22, scale: 16, nullable: true, name: 'm20_range_km')]
    private ?string $m20_range_km = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 22, scale: 16, nullable: true, name: 'm20_range_rate_km_s')]
    private ?string $m20_range_rate_km_s = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm31_ecl_lon_deg')]
    private ?string $m31_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm31_ecl_lat_deg')]
    private ?string $m31_ecl_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm43_pab_lon_deg')]
    private ?string $m43_pab_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm43_pab_lat_deg')]
    private ?string $m43_pab_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 'm43_phi_deg')]
    private ?string $m43_phi_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 's31_ecl_lon_deg')]
    private ?string $s31_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 10, nullable: true, name: 's31_ecl_lat_deg')]
    private ?string $s31_ecl_lat_deg = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, name: 'm_raw_line')]
    private ?string $m_raw_line = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, name: 's_raw_line')]
    private ?string $s_raw_line = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at_utc')]
    private ?\DateTimeImmutable $created_at_utc = null;

    public function getTsUtc(): ?\DateTimeImmutable
    {
        return $this->ts_utc;
    }

    public function setTsUtc(?\DateTimeImmutable $ts_utc): static
    {
        $this->ts_utc = $ts_utc;

        return $this;
    }

    public function getM1RaAstDeg(): ?string
    {
        return $this->m1_ra_ast_deg;
    }

    public function setM1RaAstDeg(?string $m1_ra_ast_deg): static
    {
        $this->m1_ra_ast_deg = $m1_ra_ast_deg;

        return $this;
    }

    public function getM1DecAstDeg(): ?string
    {
        return $this->m1_dec_ast_deg;
    }

    public function setM1DecAstDeg(?string $m1_dec_ast_deg): static
    {
        $this->m1_dec_ast_deg = $m1_dec_ast_deg;

        return $this;
    }

    public function getM2RaAppDeg(): ?string
    {
        return $this->m2_ra_app_deg;
    }

    public function setM2RaAppDeg(?string $m2_ra_app_deg): static
    {
        $this->m2_ra_app_deg = $m2_ra_app_deg;

        return $this;
    }

    public function getM2DecAppDeg(): ?string
    {
        return $this->m2_dec_app_deg;
    }

    public function setM2DecAppDeg(?string $m2_dec_app_deg): static
    {
        $this->m2_dec_app_deg = $m2_dec_app_deg;

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

    public function getM20RangeKm(): ?string
    {
        return $this->m20_range_km;
    }

    public function setM20RangeKm(?string $m20_range_km): static
    {
        $this->m20_range_km = $m20_range_km;

        return $this;
    }

    public function getM20RangeRateKmS(): ?string
    {
        return $this->m20_range_rate_km_s;
    }

    public function setM20RangeRateKmS(?string $m20_range_rate_km_s): static
    {
        $this->m20_range_rate_km_s = $m20_range_rate_km_s;

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

    public function getM31EclLatDeg(): ?string
    {
        return $this->m31_ecl_lat_deg;
    }

    public function setM31EclLatDeg(?string $m31_ecl_lat_deg): static
    {
        $this->m31_ecl_lat_deg = $m31_ecl_lat_deg;

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

    public function getM43PabLatDeg(): ?string
    {
        return $this->m43_pab_lat_deg;
    }

    public function setM43PabLatDeg(?string $m43_pab_lat_deg): static
    {
        $this->m43_pab_lat_deg = $m43_pab_lat_deg;

        return $this;
    }

    public function getM43PhiDeg(): ?string
    {
        return $this->m43_phi_deg;
    }

    public function setM43PhiDeg(?string $m43_phi_deg): static
    {
        $this->m43_phi_deg = $m43_phi_deg;

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

    public function getS31EclLatDeg(): ?string
    {
        return $this->s31_ecl_lat_deg;
    }

    public function setS31EclLatDeg(?string $s31_ecl_lat_deg): static
    {
        $this->s31_ecl_lat_deg = $s31_ecl_lat_deg;

        return $this;
    }

    public function getMRawLine(): ?string
    {
        return $this->m_raw_line;
    }

    public function setMRawLine(?string $m_raw_line): static
    {
        $this->m_raw_line = $m_raw_line;

        return $this;
    }

    public function getSRawLine(): ?string
    {
        return $this->s_raw_line;
    }

    public function setSRawLine(?string $s_raw_line): static
    {
        $this->s_raw_line = $s_raw_line;

        return $this;
    }

    public function getCreatedAtUtc(): ?\DateTimeImmutable
    {
        return $this->created_at_utc;
    }

    public function setCreatedAtUtc(?\DateTimeImmutable $created_at_utc): static
    {
        $this->created_at_utc = $created_at_utc;

        return $this;
    }
}
