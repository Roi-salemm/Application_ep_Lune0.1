<?php

namespace App\Entity;

use App\Repository\MoonEphemerisHourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoonEphemerisHourRepository::class)]
#[ORM\Table(name: 'moon_ephemeris_hour', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_moon_ephemeris_hour_ts', columns: ['ts_utc']),
])]
class MoonEphemerisHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?MoonNasaImport $run_id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $ts_utc = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $phase_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $illum_pct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $age_days = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $diam_km = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $dist_km = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $ra_hours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $dec_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $slon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $slat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sub_obs_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sub_obs_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $elon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $elat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $axis_a_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 14, nullable: true)]
    private ?string $delta_au = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 8, nullable: true)]
    private ?string $deldot_km_s = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_elong_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_target_obs_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_ra_hours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_dec_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_ecl_lon_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $sun_ecl_lat_deg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 14, nullable: true)]
    private ?string $sun_dist_au = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $sun_trail = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $constellation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $delta_t_sec = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $dut1_sec = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $pressure_hpa = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $temperature_c = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $raw_line = null;

    #[ORM\Column]
    private ?\DateTime $created_at_utc = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRunId(): ?MoonNasaImport
    {
        return $this->run_id;
    }

    public function setRunId(?MoonNasaImport $run_id): static
    {
        $this->run_id = $run_id;

        return $this;
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

    public function getPhaseDeg(): ?string
    {
        return $this->phase_deg;
    }

    public function setPhaseDeg(?string $phase_deg): static
    {
        $this->phase_deg = $phase_deg;

        return $this;
    }

    public function getIllumPct(): ?string
    {
        return $this->illum_pct;
    }

    public function setIllumPct(?string $illum_pct): static
    {
        $this->illum_pct = $illum_pct;

        return $this;
    }

    public function getAgeDays(): ?string
    {
        return $this->age_days;
    }

    public function setAgeDays(?string $age_days): static
    {
        $this->age_days = $age_days;

        return $this;
    }

    public function getDiamKm(): ?string
    {
        return $this->diam_km;
    }

    public function setDiamKm(?string $diam_km): static
    {
        $this->diam_km = $diam_km;

        return $this;
    }

    public function getDistKm(): ?string
    {
        return $this->dist_km;
    }

    public function setDistKm(?string $dist_km): static
    {
        $this->dist_km = $dist_km;

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

    public function getSlonDeg(): ?string
    {
        return $this->slon_deg;
    }

    public function setSlonDeg(?string $slon_deg): static
    {
        $this->slon_deg = $slon_deg;

        return $this;
    }

    public function getSlatDeg(): ?string
    {
        return $this->slat_deg;
    }

    public function setSlatDeg(?string $slat_deg): static
    {
        $this->slat_deg = $slat_deg;

        return $this;
    }

    public function getSubObsLonDeg(): ?string
    {
        return $this->sub_obs_lon_deg;
    }

    public function setSubObsLonDeg(?string $sub_obs_lon_deg): static
    {
        $this->sub_obs_lon_deg = $sub_obs_lon_deg;

        return $this;
    }

    public function getSubObsLatDeg(): ?string
    {
        return $this->sub_obs_lat_deg;
    }

    public function setSubObsLatDeg(?string $sub_obs_lat_deg): static
    {
        $this->sub_obs_lat_deg = $sub_obs_lat_deg;

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

    public function getAxisADeg(): ?string
    {
        return $this->axis_a_deg;
    }

    public function setAxisADeg(?string $axis_a_deg): static
    {
        $this->axis_a_deg = $axis_a_deg;

        return $this;
    }

    public function getDeltaAu(): ?string
    {
        return $this->delta_au;
    }

    public function setDeltaAu(?string $delta_au): static
    {
        $this->delta_au = $delta_au;

        return $this;
    }

    public function getDeldotKmS(): ?string
    {
        return $this->deldot_km_s;
    }

    public function setDeldotKmS(?string $deldot_km_s): static
    {
        $this->deldot_km_s = $deldot_km_s;

        return $this;
    }

    public function getSunElongDeg(): ?string
    {
        return $this->sun_elong_deg;
    }

    public function setSunElongDeg(?string $sun_elong_deg): static
    {
        $this->sun_elong_deg = $sun_elong_deg;

        return $this;
    }

    public function getSunTargetObsDeg(): ?string
    {
        return $this->sun_target_obs_deg;
    }

    public function setSunTargetObsDeg(?string $sun_target_obs_deg): static
    {
        $this->sun_target_obs_deg = $sun_target_obs_deg;

        return $this;
    }

    public function getSunRaHours(): ?string
    {
        return $this->sun_ra_hours;
    }

    public function setSunRaHours(?string $sun_ra_hours): static
    {
        $this->sun_ra_hours = $sun_ra_hours;

        return $this;
    }

    public function getSunDecDeg(): ?string
    {
        return $this->sun_dec_deg;
    }

    public function setSunDecDeg(?string $sun_dec_deg): static
    {
        $this->sun_dec_deg = $sun_dec_deg;

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

    public function getSunDistAu(): ?string
    {
        return $this->sun_dist_au;
    }

    public function setSunDistAu(?string $sun_dist_au): static
    {
        $this->sun_dist_au = $sun_dist_au;

        return $this;
    }

    public function getSunTrail(): ?string
    {
        return $this->sun_trail;
    }

    public function setSunTrail(?string $sun_trail): static
    {
        $this->sun_trail = $sun_trail;

        return $this;
    }

    public function getConstellation(): ?string
    {
        return $this->constellation;
    }

    public function setConstellation(?string $constellation): static
    {
        $this->constellation = $constellation;

        return $this;
    }

    public function getDeltaTSec(): ?string
    {
        return $this->delta_t_sec;
    }

    public function setDeltaTSec(?string $delta_t_sec): static
    {
        $this->delta_t_sec = $delta_t_sec;

        return $this;
    }

    public function getDut1Sec(): ?string
    {
        return $this->dut1_sec;
    }

    public function setDut1Sec(?string $dut1_sec): static
    {
        $this->dut1_sec = $dut1_sec;

        return $this;
    }

    public function getPressureHpa(): ?string
    {
        return $this->pressure_hpa;
    }

    public function setPressureHpa(?string $pressure_hpa): static
    {
        $this->pressure_hpa = $pressure_hpa;

        return $this;
    }

    public function getTemperatureC(): ?string
    {
        return $this->temperature_c;
    }

    public function setTemperatureC(?string $temperature_c): static
    {
        $this->temperature_c = $temperature_c;

        return $this;
    }

    public function getRawLine(): ?string
    {
        return $this->raw_line;
    }

    public function setRawLine(?string $raw_line): static
    {
        $this->raw_line = $raw_line;

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
