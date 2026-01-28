<?php

namespace App\Service\Moon\Horizons;

use App\Entity\MoonEphemerisHour;
use App\Service\Moon\MoonEphemerisCalculator;

final class MoonHorizonsRowMapperService
{
    private const AU_TO_KM = 149597870.7;

    public function __construct(
        private MoonHorizonsDateTimeParserService $dateTimeParser,
        private MoonEphemerisCalculator $calculator,
    ) {
    }

    /**
     * @param array{raw:string, cols:array<int, string>} $row
     * @param array<string, int|null> $columnMap
     */
    public function parseTimestamp(array $row, array $columnMap, \DateTimeZone $utc): ?\DateTime
    {
        $cols = $row['cols'] ?? [];
        $timestampValue = $this->extractColumnValue($cols, $columnMap['timestamp'] ?? null);
        if ($timestampValue === null && $cols) {
            $timestampValue = $cols[0];
        }

        return $this->dateTimeParser->parseHorizonsTimestamp($timestampValue, $utc);
    }

    /**
     * @param array{raw:string, cols:array<int, string>} $row
     * @param array<string, int|null> $columnMap
     */
    public function hydrateHour(MoonEphemerisHour $hour, array $row, array $columnMap, \DateTime $createdAt): void
    {
        $cols = $row['cols'] ?? [];

        $phaseDeg = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['phase_deg'] ?? null));
        $illumPct = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['illum_pct'] ?? null));
        $ageDays = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['age_days'] ?? null));
        $diamValue = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['diam_km'] ?? null));
        $distKmValue = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['dist_km'] ?? null));
        $deltaAu = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_au'] ?? null));
        $deldotKmS = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['deldot_km_s'] ?? null));
        $sunElong = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['sun_elong_deg'] ?? null));
        $sunTrail = $this->parseText($this->extractColumnValue($cols, $columnMap['sun_trail'] ?? null));
        $sunTargetObs = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['sun_target_obs_deg'] ?? null));
        $constellation = $this->parseText($this->extractColumnValue($cols, $columnMap['constellation'] ?? null));
        $subObsLon = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['sub_obs_lon_deg'] ?? null));
        $subObsLat = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['sub_obs_lat_deg'] ?? null));
        $deltaTSec = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_t_sec'] ?? null));
        $dut1Sec = $this->parseDecimal($this->extractColumnValue($cols, $columnMap['dut1_sec'] ?? null));

        if ($phaseDeg === null) {
            $phaseDeg = $sunTargetObs;
        }

        $distKm = $distKmValue ?? $this->parseDistanceKm($deltaAu);
        $ageComputed = $this->calculator->computeAgeDaysFromPhase($phaseDeg);
        $diamComputed = $this->calculator->computeAngularDiameterArcsec($distKm);

        $hour->setPhaseDeg($phaseDeg);
        $hour->setIllumPct($illumPct);
        $hour->setAgeDays($ageDays ?? $this->formatDecimal($ageComputed, 6));
        $hour->setDiamKm($diamValue ?? $this->formatDecimal($diamComputed, 6));
        $hour->setDistKm($distKm);
        $hour->setRaHours($this->parseRaHours($this->extractColumnValue($cols, $columnMap['ra_hours'] ?? null)));
        $hour->setDecDeg($this->parseDecDegrees($this->extractColumnValue($cols, $columnMap['dec_deg'] ?? null)));
        $hour->setSlonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['slon_deg'] ?? null)));
        $hour->setSlatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['slat_deg'] ?? null)));
        $hour->setSubObsLonDeg($subObsLon);
        $hour->setSubObsLatDeg($subObsLat);
        $hour->setElonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elon_deg'] ?? null)));
        $hour->setElatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elat_deg'] ?? null)));
        $hour->setAxisADeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['axis_a_deg'] ?? null)));
        $hour->setDeltaAu($deltaAu);
        $hour->setDeldotKmS($deldotKmS);
        $hour->setSunElongDeg($sunElong);
        $hour->setSunTrail($sunTrail);
        $hour->setSunTargetObsDeg($sunTargetObs);
        $hour->setConstellation($constellation);
        $hour->setDeltaTSec($deltaTSec);
        $hour->setDut1Sec($dut1Sec);
        $hour->setRawLine($row['raw'] ?? null);
        $hour->setCreatedAtUtc(clone $createdAt);
    }

    private function extractColumnValue(array $cols, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return $cols[$index] ?? null;
    }

    private function parseDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '' || strtolower($clean) === 'n.a.' || strtolower($clean) === 'na') {
            return null;
        }

        $clean = str_replace(['km', 'KM', 'deg', 'DEG', 'au', 'AU'], '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B\"'");
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        $direction = null;
        if (preg_match('/^([+-]?\d+(?:\.\d+)?(?:[Ee][+-]?\d+)?)([NSEW])$/i', $clean, $matches) === 1) {
            $clean = $matches[1];
            $direction = strtoupper($matches[2]);
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        if ($direction) {
            $unsigned = ltrim($clean, '+-');
            if (in_array($direction, ['S', 'W'], true)) {
                $clean = '-' . $unsigned;
            } else {
                $clean = $unsigned;
            }
        }

        return $clean;
    }

    private function parseText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean === '' ? null : $clean;
    }

    private function parseRaHours(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal !== null) {
            return $decimal;
        }

        $hours = $this->parseHmsToDecimal($value);
        return $this->formatDecimal($hours, 10);
    }

    private function parseDecDegrees(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal !== null) {
            return $decimal;
        }

        $degrees = $this->parseDmsToDecimal($value);
        return $this->formatDecimal($degrees, 10);
    }

    private function parseHmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['h', 'm', 's'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $hours = (float) $parts[0];
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $hours + ($minutes / 60.0) + ($seconds / 3600.0);
    }

    private function parseDmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['d', "'", '"'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $sign = 1.0;
        if (str_starts_with($parts[0], '-')) {
            $sign = -1.0;
        }

        $degrees = abs((float) $parts[0]);
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $sign * ($degrees + ($minutes / 60.0) + ($seconds / 3600.0));
    }

    private function parseDistanceKm(?string $value): ?string
    {
        $decimal = $this->parseDecimal($value);
        if ($decimal === null) {
            return null;
        }

        $numeric = (float) $decimal;
        if ($numeric > 0 && $numeric < 10) {
            $numeric *= self::AU_TO_KM;
        }

        return $this->formatDecimal($numeric, 6);
    }

    private function formatDecimal(?float $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
