<?php

namespace App\Service\Moon\Phase;

use App\Entity\MoonPhaseEvent;
use App\Entity\MoonEphemerisHour;
use App\Entity\SolarEphemerisHour;
use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonPhaseEventRepository;
use App\Repository\SolarEphemerisHourRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MoonPhaseEventCalculatorService
{
    private const EVENT_THRESHOLDS = [
        'new_moon' => 0.0,
        'first_quarter' => 90.0,
        'full_moon' => 180.0,
        'last_quarter' => 270.0,
    ];

    public function __construct(
        private MoonEphemerisHourRepository $moonRepository,
        private SolarEphemerisHourRepository $solarRepository,
        private MoonPhaseEventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{saved:int, updated:int, total:int}
     */
    public function calculateAndPersist(
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        \DateTimeZone $utc,
        bool $dryRun = false
    ): array {
        $moonRows = $this->moonRepository->findByTimestampRange($start, $stop);
        $solarRows = $this->solarRepository->findByTimestampRange($start, $stop);
        $solarByTimestamp = $this->indexSolarRows($solarRows);
        $existingEvents = $this->eventRepository->findByTimestampRangeIndexed($start, $stop);

        $saved = 0;
        $updated = 0;
        $total = 0;
        $cycleOffset = 0.0;
        $prevAngle = null;
        $prevAngleUnwrapped = null;
        $prevTimestamp = null;

        foreach ($moonRows as $moonRow) {
            $timestamp = $moonRow->getTsUtc();
            if (!$timestamp instanceof \DateTimeInterface) {
                continue;
            }

            $timestampKey = $timestamp->format('Y-m-d H:i');
            $solarRow = $solarByTimestamp[$timestampKey] ?? null;
            $angle = $this->computeElongationAngle($moonRow, $solarRow);
            if ($angle === null) {
                continue;
            }

            $angle = $this->normalizeAngle($angle);
            if ($prevAngle !== null && $angle < ($prevAngle - 180.0)) {
                $cycleOffset += 360.0;
            }

            $angleUnwrapped = $angle + $cycleOffset;

            if ($prevAngleUnwrapped !== null && $prevTimestamp instanceof \DateTimeInterface) {
                $events = $this->findEventsBetween(
                    $prevTimestamp,
                    $timestamp,
                    $prevAngleUnwrapped,
                    $angleUnwrapped,
                    $utc
                );

                foreach ($events as $eventData) {
                    $total++;
                    $eventKey = $eventData['type'] . '|' . $eventData['timestamp']->format('Y-m-d H:i');
                    if ($dryRun) {
                        if (isset($existingEvents[$eventKey])) {
                            $updated++;
                        } else {
                            $saved++;
                        }
                        continue;
                    }
                    if (isset($existingEvents[$eventKey])) {
                        $event = $existingEvents[$eventKey];
                        $event->setPhaseDeg($eventData['phase_deg']);
                        $event->setPrecisionSec($eventData['precision_sec']);
                        $event->setSource($eventData['source']);
                        $updated++;
                    } else {
                        $event = new MoonPhaseEvent();
                        $event->setEventType($eventData['type']);
                        $event->setTsUtc(\DateTime::createFromImmutable($eventData['timestamp']));
                        $event->setPhaseDeg($eventData['phase_deg']);
                        $event->setPrecisionSec($eventData['precision_sec']);
                        $event->setSource($eventData['source']);
                        $event->setCreatedAtUtc(new \DateTime('now', $utc));
                        $this->entityManager->persist($event);
                        $existingEvents[$eventKey] = $event;
                        $saved++;
                    }
                }
            }

            $prevAngle = $angle;
            $prevAngleUnwrapped = $angleUnwrapped;
            $prevTimestamp = $timestamp;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return [
            'saved' => $saved,
            'updated' => $updated,
            'total' => $total,
        ];
    }

    /**
     * @param SolarEphemerisHour[] $solarRows
     * @return array<string, SolarEphemerisHour>
     */
    private function indexSolarRows(array $solarRows): array
    {
        $indexed = [];
        foreach ($solarRows as $row) {
            $timestamp = $row->getTsUtc();
            if (!$timestamp instanceof \DateTimeInterface) {
                continue;
            }
            $indexed[$timestamp->format('Y-m-d H:i')] = $row;
        }

        return $indexed;
    }

    private function computeElongationAngle(MoonEphemerisHour $moon, ?SolarEphemerisHour $solar): ?float
    {
        $moonLon = $moon->getElonDeg();
        $solarLon = $solar?->getElonDeg();
        if ($moonLon !== null && $solarLon !== null) {
            return (float) $moonLon - (float) $solarLon;
        }

        $phaseAngle = $moon->getSunTargetObsDeg();
        if ($phaseAngle === null) {
            return null;
        }

        $elong = 180.0 - (float) $phaseAngle;
        if ($elong < 0.0) {
            $elong = 0.0;
        }

        $trail = $moon->getSunTrail();
        if ($trail === '/L') {
            return 360.0 - $elong;
        }

        if ($trail === '/T') {
            return $elong;
        }

        return $elong;
    }

    /**
     * @return array<int, array{type:string, timestamp:\DateTimeImmutable, phase_deg:string, precision_sec:int, source:string}>
     */
    private function findEventsBetween(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        float $startAngle,
        float $endAngle,
        \DateTimeZone $utc
    ): array {
        $events = [];
        $min = min($startAngle, $endAngle);
        $max = max($startAngle, $endAngle);

        $startCycle = (int) floor($min / 360.0);
        $endCycle = (int) floor($max / 360.0);

        for ($cycle = $startCycle; $cycle <= $endCycle; $cycle++) {
            foreach (self::EVENT_THRESHOLDS as $type => $threshold) {
                $target = ($cycle * 360.0) + $threshold;
                if ($target <= $min || $target > $max) {
                    continue;
                }

                $timestamp = $this->interpolateTimestamp($startTime, $endTime, $startAngle, $endAngle, $target, $utc);
                $events[] = [
                    'type' => $type,
                    'timestamp' => $timestamp,
                    'phase_deg' => $this->formatDecimal($threshold, 2),
                    'precision_sec' => 60,
                    'source' => 'geocentric',
                ];
            }
        }

        return $events;
    }

    private function interpolateTimestamp(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
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

    private function formatDecimal(float $value, int $scale): string
    {
        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
