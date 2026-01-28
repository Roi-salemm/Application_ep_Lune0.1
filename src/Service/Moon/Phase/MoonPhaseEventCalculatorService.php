<?php

namespace App\Service\Moon\Phase;

use App\Entity\MoonPhaseEvent;
use App\Entity\MoonEphemerisHour;
use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonPhaseEventRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MoonPhaseEventCalculatorService
{
    public function __construct(
        private MoonEphemerisHourRepository $moonRepository,
        private MoonPhaseEventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private MoonPhaseDefinitions $phaseDefinitions,
    ) {
    }

    /**
     * @return array{saved:int, updated:int, total:int}
     */
    public function calculateAndPersist(
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        \DateTimeZone $utc,
        bool $dryRun = false,
        ?\DateTimeInterface $eventStart = null,
        ?\DateTimeInterface $eventStop = null,
    ): array {
        $moonRows = $this->moonRepository->findByTimestampRange($start, $stop);
        $rangeStart = $eventStart ?? $start;
        $rangeStop = $eventStop ?? $stop;
        $existingEvents = $this->eventRepository->findByTimestampRangeIndexed($rangeStart, $rangeStop);
        $definitions = $this->phaseDefinitions->all();

        $saved = 0;
        $updated = 0;
        $total = 0;
        $cycleOffset = 0.0;
        $prevElong = null;
        $prevTrend = null;
        $prevAngle = null;
        $prevAngleUnwrapped = null;
        $prevTimestamp = null;

        foreach ($moonRows as $moonRow) {
            $timestamp = $moonRow->getTsUtc();
            if (!$timestamp instanceof \DateTimeInterface) {
                continue;
            }

            $timestampKey = $timestamp->format('Y-m-d H:i');
            $angleData = $this->computePhaseAngle($moonRow, $prevElong, $prevTrend);
            $angle = $angleData['angle'];
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
                    $definitions,
                    $utc
                );

                foreach ($events as $eventData) {
                    if ($eventStart && $eventData['timestamp'] < $eventStart) {
                        continue;
                    }
                    if ($eventStop && $eventData['timestamp'] >= $eventStop) {
                        continue;
                    }
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
                        $event->setPhaseName($eventData['phase_name']);
                        $event->setDisplayAtUtc(\DateTime::createFromImmutable($eventData['display_at']));
                        $event->setIllumPct($eventData['illum_pct']);
                        $event->setPrecisionSec($eventData['precision_sec']);
                        $event->setSource($eventData['source']);
                        $updated++;
                    } else {
                        $event = new MoonPhaseEvent();
                        $event->setEventType($eventData['type']);
                        $event->setTsUtc(\DateTime::createFromImmutable($eventData['timestamp']));
                        $event->setPhaseName($eventData['phase_name']);
                        $event->setDisplayAtUtc(\DateTime::createFromImmutable($eventData['display_at']));
                        $event->setPhaseDeg($eventData['phase_deg']);
                        $event->setIllumPct($eventData['illum_pct']);
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
            $prevElong = $angleData['elong'] ?? $prevElong;
            $prevTrend = $angleData['trend'] ?? $prevTrend;
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
     * @return array{saved:int, updated:int, total:int}
     */
    public function calculateAndPersistMonth(
        \DateTimeInterface $monthStart,
        \DateTimeInterface $monthStop,
        \DateTimeZone $utc,
        bool $dryRun = false
    ): array {
        $dataStart = (new \DateTimeImmutable($monthStart->format('Y-m-d H:i:s'), $utc))
            ->modify('-2 hours');
        $dataStop = (new \DateTimeImmutable($monthStop->format('Y-m-d H:i:s'), $utc))
            ->modify('+2 hours');

        return $this->calculateAndPersist($dataStart, $dataStop, $utc, $dryRun, $monthStart, $monthStop);
    }

    /**
     * @return array{angle: ?float, elong: ?float, trend: ?string}
     */
    private function computePhaseAngle(MoonEphemerisHour $moon, ?float $prevElong, ?string $prevTrend): array
    {
        $moonLon = $moon->getElonDeg();
        $solarLon = $moon->getSunEclLonDeg();
        if ($moonLon !== null && $solarLon !== null) {
            $angle = (float) $moonLon - (float) $solarLon;
            $angle = $this->normalizeAngle($angle);

            return ['angle' => $angle, 'elong' => null, 'trend' => $prevTrend];
        }

        $elong = $this->computeElongationBase($moon);
        if ($elong === null) {
            return ['angle' => null, 'elong' => null, 'trend' => $prevTrend];
        }

        $trend = $prevTrend;
        if ($prevElong !== null) {
            $delta = $elong - $prevElong;
            if (abs($delta) > 1e-6) {
                $trend = $delta > 0 ? 'up' : 'down';
            }
        }
        if ($trend === null) {
            $trend = 'up';
        }

        $angle = $trend === 'down' ? (360.0 - $elong) : $elong;

        return ['angle' => $angle, 'elong' => $elong, 'trend' => $trend];
    }

    private function computeElongationBase(MoonEphemerisHour $moon): ?float
    {
        $sunElong = $moon->getSunElongDeg();
        if ($sunElong !== null) {
            return $this->clampElongation((float) $sunElong);
        }

        $phaseAngle = $moon->getSunTargetObsDeg();
        if ($phaseAngle !== null) {
            $elong = 180.0 - (float) $phaseAngle;
            return $this->clampElongation($elong);
        }

        return null;
    }

    /**
     * @param array<string, array{angle: float, label: string}> $definitions
     * @return array<int, array{type:string, timestamp:\DateTimeImmutable, display_at:\DateTimeImmutable, phase_name:string, phase_deg:string, illum_pct:string, precision_sec:int, source:string}>
     */
    private function findEventsBetween(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        float $startAngle,
        float $endAngle,
        array $definitions,
        \DateTimeZone $utc
    ): array {
        $events = [];
        $min = min($startAngle, $endAngle);
        $max = max($startAngle, $endAngle);

        $startCycle = (int) floor($min / 360.0);
        $endCycle = (int) floor($max / 360.0);

        for ($cycle = $startCycle; $cycle <= $endCycle; $cycle++) {
            foreach ($definitions as $type => $definition) {
                $threshold = $definition['angle'];
                $target = ($cycle * 360.0) + $threshold;
                if ($target <= $min || $target > $max) {
                    continue;
                }

                $timestamp = $this->interpolateTimestamp($startTime, $endTime, $startAngle, $endAngle, $target, $utc);
                $illumPct = $this->computeIlluminationPercent($threshold);
                $events[] = [
                    'type' => $type,
                    'timestamp' => $timestamp,
                    'display_at' => $timestamp,
                    'phase_name' => $definition['label'],
                    'phase_deg' => $this->formatDecimal($threshold, 2),
                    'illum_pct' => $this->formatDecimal($illumPct, 2),
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

    private function clampElongation(float $elong): float
    {
        if ($elong < 0.0) {
            return 0.0;
        }
        if ($elong > 180.0) {
            return 180.0;
        }

        return $elong;
    }

    private function formatDecimal(float $value, int $scale): string
    {
        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function computeIlluminationPercent(float $elongationDeg): float
    {
        $radians = deg2rad($elongationDeg);
        return 50.0 * (1.0 - cos($radians));
    }
}
