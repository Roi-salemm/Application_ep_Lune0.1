<?php

/**
 * Construit la table ms_mapping depuis canonique_data (une ligne par jour).
 * Pourquoi: fournir un dataset journalier + phase + heure de changement.
 */

namespace App\Service\Moon;

use App\Repository\CanoniqueDataRepository;
use App\Repository\MsMappingRepository;

final class MsMappingParseService
{
    /**
     * Angles des 8 phases (en degres), index = code phase.
     */
    private const PHASE_THRESHOLDS = [
        0 => 0.0,
        1 => 45.0,
        2 => 90.0,
        3 => 135.0,
        4 => 180.0,
        5 => 225.0,
        6 => 270.0,
        7 => 315.0,
    ];

    public function __construct(
        private CanoniqueDataRepository $canoniqueRepository,
        private MsMappingRepository $mappingRepository,
    ) {
    }

    /**
     * @return array{saved:int, updated:int, missing_days:string[], events:int}
     */
    public function parseMonth(
        \DateTimeImmutable $monthStart,
        \DateTimeImmutable $monthStop,
        \DateTimeZone $utc
    ): array {
        // Meme bornage que le parse canonique: mois UTC (00:00 -> mois suivant).
        $dataStart = $monthStart->modify('-2 hours');
        $dataStop = $monthStop->modify('+2 hours');

        $rows = $this->canoniqueRepository->findByTimestampRange($dataStart, $dataStop);
        if (!$rows) {
            return ['saved' => 0, 'updated' => 0, 'missing_days' => [], 'events' => 0];
        }

        $indexed = [];
        $series = [];
        foreach ($rows as $row) {
            $timestamp = $this->parseTimestamp($row['ts_utc'] ?? null, $utc);
            if (!$timestamp) {
                continue;
            }
            $key = $timestamp->format('Y-m-d H:i:s');
            $indexed[$key] = $row;

            $moonLon = $this->toFloat($row['m31_ecl_lon_deg'] ?? null);
            $sunLon = $this->toFloat($row['s31_ecl_lon_deg'] ?? null);
            if ($moonLon === null || $sunLon === null) {
                continue;
            }
            $angle = $this->normalizeAngle($moonLon - $sunLon);
            $series[] = [
                'ts' => $timestamp,
                'angle' => $angle,
            ];
        }

        $events = $this->computePhaseEvents($series, $utc);
        usort(
            $events,
            static fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']
        );

        $eventsByDay = [];
        foreach ($events as $event) {
            $timestamp = $event['timestamp'];
            if ($timestamp < $monthStart || $timestamp >= $monthStop) {
                continue;
            }
            $dayKey = $timestamp->format('Y-m-d');
            $eventsByDay[$dayKey] = $event;
        }

        $missingDays = [];
        $hasMidday = false;
        $cursor = $monthStart;
        while ($cursor < $monthStop) {
            $midday = $cursor->setTime(12, 0, 0);
            $middayKey = $midday->format('Y-m-d H:i:s');
            if (isset($indexed[$middayKey])) {
                $hasMidday = true;
            } else {
                $missingDays[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
        }

        if (!$hasMidday) {
            return [
                'saved' => 0,
                'updated' => 0,
                'missing_days' => $missingDays,
                'events' => count($eventsByDay),
            ];
        }

        $this->mappingRepository->deleteByTimestampRange($monthStart, $monthStop);

        $saved = 0;
        $updated = 0;
        $currentPhase = null;
        $eventIndex = 0;
        $eventCount = count($events);

        $cursor = $monthStart;
        while ($cursor < $monthStop) {
            $midday = $cursor->setTime(12, 0, 0);
            $middayKey = $midday->format('Y-m-d H:i:s');
            $row = $indexed[$middayKey] ?? null;
            if (!$row) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            while ($eventIndex < $eventCount && $events[$eventIndex]['timestamp'] <= $midday) {
                $currentPhase = $events[$eventIndex]['phase'];
                $eventIndex++;
            }

            $phase = $currentPhase;
            if ($phase === null) {
                $phase = $this->computePhaseCode(
                    $this->toFloat($row['m31_ecl_lon_deg'] ?? null),
                    $this->toFloat($row['s31_ecl_lon_deg'] ?? null)
                );
            }
            $dayKey = $cursor->format('Y-m-d');
            $eventForDay = $eventsByDay[$dayKey] ?? null;
            if ($eventForDay) {
                $phase = $eventForDay['phase'];
            }
            $phaseHour = $eventForDay['timestamp'] ?? null;

            $result = $this->mappingRepository->upsertRow([
                'ts_utc' => $midday->format('Y-m-d H:i:s'),
                'm43_pab_lon_deg' => $row['m43_pab_lon_deg'] ?? null,
                'm10_illum_frac' => $row['m10_illum_frac'] ?? null,
                'm31_ecl_lon_deg' => $row['m31_ecl_lon_deg'] ?? null,
                's31_ecl_lon_deg' => $row['s31_ecl_lon_deg'] ?? null,
                'phase' => $phase,
                'phase_hour' => $phaseHour?->format('Y-m-d H:i:s'),
            ]);

            if ($result === 'insert') {
                $saved++;
            } else {
                $updated++;
            }

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'saved' => $saved,
            'updated' => $updated,
            'missing_days' => $missingDays,
            'events' => count($eventsByDay),
        ];
    }

    private function parseTimestamp(mixed $value, \DateTimeZone $utc): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone($utc);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, $utc);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param array<int, array{ts:\DateTimeImmutable, angle:float}> $series
     * @return array<int, array{timestamp:\DateTimeImmutable, phase:int}>
     */
    private function computePhaseEvents(array $series, \DateTimeZone $utc): array
    {
        if (count($series) < 2) {
            return [];
        }

        usort(
            $series,
            static fn (array $a, array $b) => $a['ts'] <=> $b['ts']
        );

        $events = [];
        $cycleOffset = 0.0;
        $prevAngle = null;
        $prevAngleUnwrapped = null;
        $prevTimestamp = null;

        foreach ($series as $point) {
            $angle = $this->normalizeAngle($point['angle']);
            if ($prevAngle !== null && $angle < ($prevAngle - 180.0)) {
                $cycleOffset += 360.0;
            }
            $angleUnwrapped = $angle + $cycleOffset;

            if ($prevAngleUnwrapped !== null && $prevTimestamp instanceof \DateTimeImmutable) {
                $events = array_merge(
                    $events,
                    $this->findEventsBetween(
                        $prevTimestamp,
                        $point['ts'],
                        $prevAngleUnwrapped,
                        $angleUnwrapped,
                        $utc
                    )
                );
            }

            $prevAngle = $angle;
            $prevAngleUnwrapped = $angleUnwrapped;
            $prevTimestamp = $point['ts'];
        }

        return $events;
    }

    /**
     * @return array<int, array{timestamp:\DateTimeImmutable, phase:int}>
     */
    private function findEventsBetween(
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        float $startAngle,
        float $endAngle,
        \DateTimeZone $utc
    ): array {
        if ($endTime <= $startTime) {
            return [];
        }

        $events = [];
        $min = min($startAngle, $endAngle);
        $max = max($startAngle, $endAngle);

        $startCycle = (int) floor($min / 360.0);
        $endCycle = (int) floor($max / 360.0);

        for ($cycle = $startCycle; $cycle <= $endCycle; $cycle++) {
            foreach (self::PHASE_THRESHOLDS as $phase => $threshold) {
                $target = ($cycle * 360.0) + $threshold;
                if ($target <= $min || $target > $max) {
                    continue;
                }
                $timestamp = $this->interpolateTimestamp(
                    $startTime,
                    $endTime,
                    $startAngle,
                    $endAngle,
                    $target,
                    $utc
                );
                $events[] = ['timestamp' => $timestamp, 'phase' => $phase];
            }
        }

        return $events;
    }

    private function interpolateTimestamp(
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        float $startAngle,
        float $endAngle,
        float $targetAngle,
        \DateTimeZone $utc
    ): \DateTimeImmutable {
        $startTs = $startTime->getTimestamp();
        $endTs = $endTime->getTimestamp();
        $deltaSeconds = $endTs - $startTs;

        if ($deltaSeconds <= 0 || $endAngle === $startAngle) {
            return (new \DateTimeImmutable('@' . $startTs))->setTimezone($utc);
        }

        $ratio = ($targetAngle - $startAngle) / ($endAngle - $startAngle);
        $ratio = min(1.0, max(0.0, $ratio));
        $timestamp = (int) round($startTs + ($ratio * $deltaSeconds));
        $timestamp = (int) round($timestamp / 60) * 60;

        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($utc);
    }

    private function normalizeAngle(float $angle): float
    {
        $normalized = fmod($angle, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        return $normalized;
    }

    private function computePhaseCode(?float $moonLon, ?float $sunLon): ?int
    {
        if ($moonLon === null || $sunLon === null) {
            return null;
        }

        $angle = $this->normalizeAngle($moonLon - $sunLon);
        $phase = (int) floor($angle / 45.0);

        return max(0, min(7, $phase));
    }
}
