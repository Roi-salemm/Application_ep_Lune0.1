<?php

namespace App\Service\Solar\Horizons;

use App\Entity\SolarEphemerisHour;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;

final class SolarHorizonsRowMapperService
{
    public function __construct(
        private MoonHorizonsDateTimeParserService $dateTimeParser,
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
    public function hydrateHour(SolarEphemerisHour $hour, array $row, array $columnMap, \DateTime $createdAt): void
    {
        $cols = $row['cols'] ?? [];

        $hour->setRaHours($this->parseRaHours($this->extractColumnValue($cols, $columnMap['ra_hours'] ?? null)));
        $hour->setDecDeg($this->parseDecDegrees($this->extractColumnValue($cols, $columnMap['dec_deg'] ?? null)));
        $hour->setElonDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elon_deg'] ?? null)));
        $hour->setElatDeg($this->parseDecimal($this->extractColumnValue($cols, $columnMap['elat_deg'] ?? null)));
        $hour->setDistAu($this->parseDecimal($this->extractColumnValue($cols, $columnMap['delta_au'] ?? null)));
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

        $clean = str_replace(['km', 'KM', 'deg', 'DEG'], '', $clean);
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        return $clean;
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

    private function formatDecimal(?float $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
