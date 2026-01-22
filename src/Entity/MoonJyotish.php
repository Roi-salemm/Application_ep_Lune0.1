<?php

namespace App\Entity;

use App\Repository\MoonJyotishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoonJyotishRepository::class)]
class MoonJyotish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 10, nullable: true)]
    private ?string $moon_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $moon_ecl_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $sun_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $sun_ecl_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $ayanamsa_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $moon_sid_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    private ?string $sun_sid_lon_deg = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $tithi_index = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $nakshatra_index = null;

    // #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 10, nullable: true)]
    // private ?string $rashi_index = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $rashi_index = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMoonEclLonDeg(): ?string
    {
        return $this->moon_ecl_lon_deg;
    }

    public function setMoonEclLonDeg(?string $moon_ecl_lon_deg): static
    {
        $this->moon_ecl_lon_deg = $moon_ecl_lon_deg;

        return $this;
    }

    public function getMoonEclLatDeg(): ?string
    {
        return $this->moon_ecl_lat_deg;
    }

    public function setMoonEclLatDeg(?string $moon_ecl_lat_deg): static
    {
        $this->moon_ecl_lat_deg = $moon_ecl_lat_deg;

        return $this;
    }

    public function getSunEclLonDeg(): ?string
    {
        return $this->sun_ecl_lon_deg;
    }

    public function setSunEclLonDeg(?string $sun_ecl_lon_deg): static
    {
        $this->sun_ecl_lon_deg = $sun_ecl_lon_deg;

        return $this;
    }

    public function getSunEclLatDeg(): ?string
    {
        return $this->sun_ecl_lat_deg;
    }

    public function setSunEclLatDeg(?string $sun_ecl_lat_deg): static
    {
        $this->sun_ecl_lat_deg = $sun_ecl_lat_deg;

        return $this;
    }

    public function getAyanamsaDeg(): ?string
    {
        return $this->ayanamsa_deg;
    }

    public function setAyanamsaDeg(?string $ayanamsa_deg): static
    {
        $this->ayanamsa_deg = $ayanamsa_deg;

        return $this;
    }

    public function getMoonSidLonDeg(): ?string
    {
        return $this->moon_sid_lon_deg;
    }

    public function setMoonSidLonDeg(?string $moon_sid_lon_deg): static
    {
        $this->moon_sid_lon_deg = $moon_sid_lon_deg;

        return $this;
    }

    public function getSunSidLonDeg(): ?string
    {
        return $this->sun_sid_lon_deg;
    }

    public function setSunSidLonDeg(?string $sun_sid_lon_deg): static
    {
        $this->sun_sid_lon_deg = $sun_sid_lon_deg;

        return $this;
    }

    public function getTithiIndex(): ?int
    {
        return $this->tithi_index;
    }

    public function setTithiIndex(?int $tithi_index): static
    {
        $this->tithi_index = $tithi_index;

        return $this;
    }

    public function getNakshatraIndex(): ?int
    {
        return $this->nakshatra_index;
    }

    public function setNakshatraIndex(?int $nakshatra_index): static
    {
        $this->nakshatra_index = $nakshatra_index;

        return $this;
    }

    public function getRashiIndex(): ?int
    {
        return $this->rashi_index;
    }

    public function setRashiIndex(?int $rashi_index): static
    {
        $this->rashi_index = $rashi_index;

        return $this;
    }
}
