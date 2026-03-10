<?php

namespace App\Service\Moon;

use App\Entity\OrbWindow;
use App\Repository\MsMappingRepository;
use App\Repository\OrbWindowRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de parse vers orb_window.
 * Pourquoi: centraliser le calcul des fenetres temporelles autour des phases astronomiques exactes (phase_hour UTC).
 * Info: deux methodes sont supportees:
 *   - midpoint_partition_v1 (influence_orb)
 *   - multi_section_phase_v1 (multi_section_phase)
 */
final class OrbWindowParseService
{
    public const FAMILY_INFLUENCE_ORB = 'influence_orb';
    public const FAMILY_MULTI_SECTION_PHASE = 'multi_section_phase';
    public const METHOD_INFLUENCE_ORB = 'midpoint_partition_v1';
    public const METHOD_MULTI_SECTION_PHASE = 'multi_section_phase_v1';

    private const HALF_CORE_SECONDS = 216000; // 2.5 jours
    private const ONE_DAY_SECONDS = 86400;

    /**
     * @return array{window_family:string,calculation_method:string,deleted:int,created:int,source_events:int}
     */
    public function parseRange(
        string $windowFamily,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        EntityManagerInterface $entityManager,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository
    ): array {
        $family = $this->sanitizeFamily($windowFamily);
        $method = $this->resolveCalculationMethod($family);
        if ($method === null) {
            throw new \InvalidArgumentException(sprintf('Aucune methode de calcul pour la famille "%s".', $family));
        }

        $utc = new \DateTimeZone('UTC');
        $startUtc = $startUtc->setTimezone($utc);
        $endUtc = $endUtc->setTimezone($utc);
        if ($endUtc <= $startUtc) {
            throw new \InvalidArgumentException('La plage UTC est invalide.');
        }

        // Marge pour disposer d'un voisin avant/apres aux bornes de la plage.
        $searchStart = $startUtc->modify('-45 days');
        $searchEnd = $endUtc->modify('+45 days');
        $events = $this->validateOrderedUniqueEvents(
            $msMappingRepository->findPhaseEventsByPhaseHourRange($searchStart, $searchEnd)
        );
        $deleted = $orbWindowRepository->deleteByFamilyMethodAndEventRange($family, $method, $startUtc, $endUtc);
        if ($events === []) {
            return [
                'window_family' => $family,
                'calculation_method' => $method,
                'deleted' => $deleted,
                'created' => 0,
                'source_events' => 0,
            ];
        }

        if ($family === self::FAMILY_MULTI_SECTION_PHASE) {
            $result = $this->parseMultiSectionPhase(
                $events,
                $family,
                $method,
                $startUtc,
                $endUtc,
                $entityManager
            );
        } else {
            $result = $this->parseInfluenceOrb(
                $events,
                $family,
                $method,
                $startUtc,
                $endUtc,
                $entityManager,
                $utc
            );
        }

        $entityManager->flush();

        return [
            'window_family' => $family,
            'calculation_method' => $method,
            'deleted' => $deleted,
            'created' => $result['created'],
            'source_events' => $result['source_events'],
        ];
    }

    /**
     * @return string[]
     */
    public function supportedFamilies(): array
    {
        return [
            self::FAMILY_INFLUENCE_ORB,
            self::FAMILY_MULTI_SECTION_PHASE,
        ];
    }

    public function resolveCalculationMethod(string $windowFamily): ?string
    {
        return match ($this->sanitizeFamily($windowFamily)) {
            self::FAMILY_INFLUENCE_ORB => self::METHOD_INFLUENCE_ORB,
            self::FAMILY_MULTI_SECTION_PHASE => self::METHOD_MULTI_SECTION_PHASE,
            default => null,
        };
    }

    private function sanitizeFamily(string $windowFamily): string
    {
        return strtolower(trim($windowFamily));
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $events
     * @return array{created:int,source_events:int}
     */
    private function parseInfluenceOrb(
        array $events,
        string $family,
        string $method,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        EntityManagerInterface $entityManager,
        \DateTimeZone $utc
    ): array {
        $eventCountInRange = $this->countEventsInRange($events, $startUtc, $endUtc);
        $total = count($events);
        if ($total < 3) {
            return ['created' => 0, 'source_events' => $eventCountInRange];
        }

        $lunationKeysByEvent = $this->buildLunationKeysByEvent($events);
        $created = 0;

        // Regle explicite: premier et dernier evenements ignores (pas de voisin des deux cotes).
        for ($i = 1; $i < ($total - 1); $i++) {
            $event = $events[$i];
            $eventAt = $event['phase_hour']->setTimezone($utc);
            $eventTs = $eventAt->getTimestamp();
            if ($eventTs < $startUtc->getTimestamp() || $eventTs >= $endUtc->getTimestamp()) {
                continue;
            }

            $previousTs = $events[$i - 1]['phase_hour']->getTimestamp();
            $nextTs = $events[$i + 1]['phase_hour']->getTimestamp();
            $startTs = $this->midpointTimestamp($previousTs, $eventTs);
            $endTs = $this->midpointTimestamp($eventTs, $nextTs);
            if ($endTs <= $startTs) {
                continue;
            }

            $window = new OrbWindow();
            $window->setWindowFamily($family);
            $window->setPhaseKey($this->phaseKeyFromInt((int) $event['phase']));
            $window->setEventAtUtc($eventAt);
            $window->setStartsAtUtc((new \DateTimeImmutable('@' . $startTs))->setTimezone($utc));
            $window->setEndsAtUtc((new \DateTimeImmutable('@' . $endTs))->setTimezone($utc));
            $window->setLunationKey($lunationKeysByEvent[$i] ?? null);
            $window->setSequenceNo(null);
            $window->setCalculationMethod($method);
            $entityManager->persist($window);
            $created++;
        }

        return ['created' => $created, 'source_events' => $eventCountInRange];
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $events
     * @return array{created:int,source_events:int}
     */
    private function parseMultiSectionPhase(
        array $events,
        string $family,
        string $method,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        EntityManagerInterface $entityManager
    ): array {
        $anchors = array_values(array_filter(
            $events,
            static fn (array $event): bool => in_array((int) ($event['phase'] ?? -1), [0, 4], true)
        ));

        $sourceEvents = $this->countEventsInRange($anchors, $startUtc, $endUtc);
        if ($anchors === []) {
            return ['created' => 0, 'source_events' => $sourceEvents];
        }

        $utc = new \DateTimeZone('UTC');
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $lunationKeysByAnchor = $this->buildLunationKeysByAnchor($anchors);

        $created = 0;
        $total = count($anchors);
        for ($i = 0; $i < $total; $i++) {
            $anchor = $anchors[$i];
            $phase = (int) $anchor['phase'];
            $eventAt = $anchor['phase_hour']->setTimezone($utc);
            $eventTs = $eventAt->getTimestamp();

            if ($eventTs >= $startTs && $eventTs < $endTs) {
                $prefix = $phase === 4 ? 'full_moon' : 'new_moon';
                $frameStartTs = $eventTs - self::HALF_CORE_SECONDS - self::ONE_DAY_SECONDS;
                $leadEndTs = $eventTs - self::HALF_CORE_SECONDS;
                $coreEndTs = $eventTs + self::HALF_CORE_SECONDS;
                $frameEndTs = $eventTs + self::HALF_CORE_SECONDS + self::ONE_DAY_SECONDS;
                $lunationKey = $lunationKeysByAnchor[$i] ?? null;

                $created += $this->persistOrbWindow(
                    $entityManager,
                    $family,
                    $method,
                    $prefix . '_edge_start',
                    $eventAt,
                    $frameStartTs,
                    $leadEndTs,
                    $lunationKey,
                    1
                ) ? 1 : 0;
                $created += $this->persistOrbWindow(
                    $entityManager,
                    $family,
                    $method,
                    $prefix . '_core_before',
                    $eventAt,
                    $leadEndTs,
                    $eventTs,
                    $lunationKey,
                    2
                ) ? 1 : 0;
                $created += $this->persistOrbWindow(
                    $entityManager,
                    $family,
                    $method,
                    $prefix . '_core_after',
                    $eventAt,
                    $eventTs,
                    $coreEndTs,
                    $lunationKey,
                    3
                ) ? 1 : 0;
                $created += $this->persistOrbWindow(
                    $entityManager,
                    $family,
                    $method,
                    $prefix . '_edge_end',
                    $eventAt,
                    $coreEndTs,
                    $frameEndTs,
                    $lunationKey,
                    4
                ) ? 1 : 0;

                if ($i < ($total - 1)) {
                    $next = $anchors[$i + 1];
                    $nextTs = $next['phase_hour']->setTimezone($utc)->getTimestamp();
                    $trendStartTs = $frameEndTs;
                    $trendEndTs = $nextTs - self::HALF_CORE_SECONDS - self::ONE_DAY_SECONDS;
                    $trendPhaseKey = $this->trendPhaseKey($phase, (int) $next['phase']);

                    $created += $this->persistOrbWindow(
                        $entityManager,
                        $family,
                        $method,
                        $trendPhaseKey,
                        $eventAt,
                        $trendStartTs,
                        $trendEndTs,
                        $lunationKey,
                        5
                    ) ? 1 : 0;
                }
            }
        }

        return ['created' => $created, 'source_events' => $sourceEvents];
    }

    private function trendPhaseKey(int $fromPhase, int $toPhase): string
    {
        if ($fromPhase === 0 && $toPhase === 4) {
            return 'influence_croissante';
        }
        if ($fromPhase === 4 && $toPhase === 0) {
            return 'influence_decroissante';
        }

        return $fromPhase === 4 ? 'influence_decroissante' : 'influence_croissante';
    }

    private function persistOrbWindow(
        EntityManagerInterface $entityManager,
        string $windowFamily,
        string $calculationMethod,
        string $phaseKey,
        \DateTimeImmutable $eventAtUtc,
        int $startTs,
        int $endTs,
        ?string $lunationKey,
        ?int $sequenceNo
    ): bool {
        if ($endTs <= $startTs) {
            return false;
        }

        $utc = new \DateTimeZone('UTC');
        $window = new OrbWindow();
        $window->setWindowFamily($windowFamily);
        $window->setPhaseKey($phaseKey);
        $window->setEventAtUtc($eventAtUtc);
        $window->setStartsAtUtc((new \DateTimeImmutable('@' . $startTs))->setTimezone($utc));
        $window->setEndsAtUtc((new \DateTimeImmutable('@' . $endTs))->setTimezone($utc));
        $window->setLunationKey($lunationKey);
        $window->setSequenceNo($sequenceNo);
        $window->setCalculationMethod($calculationMethod);

        $entityManager->persist($window);

        return true;
    }

    private function phaseKeyFromInt(int $phase): string
    {
        return match ($phase) {
            0 => 'new_moon',
            1 => 'waxing_crescent',
            2 => 'first_quarter',
            3 => 'waxing_gibbous',
            4 => 'full_moon',
            5 => 'waning_gibbous',
            6 => 'last_quarter',
            7 => 'waning_crescent',
            default => 'phase_' . $phase,
        };
    }

    /**
     * Verifie que la serie est strictement croissante et sans doublon temporel.
     *
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $events
     * @return array<int, array{phase:int, phase_hour:\DateTimeImmutable}>
     */
    private function validateOrderedUniqueEvents(array $events): array
    {
        if ($events === []) {
            return [];
        }

        usort(
            $events,
            static fn (array $a, array $b): int =>
                $a['phase_hour']->getTimestamp() <=> $b['phase_hour']->getTimestamp()
        );

        $lastTs = null;
        foreach ($events as $event) {
            $ts = $event['phase_hour']->getTimestamp();
            if ($lastTs !== null && $ts <= $lastTs) {
                throw new \RuntimeException('Serie d evenements non strictement croissante (doublon ou inversion).');
            }
            $lastTs = $ts;
        }

        return $events;
    }

    /**
     * Milieu exact entre deux timestamps.
     * Deterministe: arithmetique entiere, sans flottants.
     */
    private function midpointTimestamp(int $leftTs, int $rightTs): int
    {
        if ($rightTs <= $leftTs) {
            throw new \RuntimeException('Midpoint impossible: rightTs doit etre strictement superieur a leftTs.');
        }

        return $leftTs + intdiv($rightTs - $leftTs, 2);
    }

    /**
     * Associe chaque evenement a sa lunaison via une cle lisible.
     * Format: NN_YYYYMMDDHHMMSS (NN = numero de lune dans l'annee du new moon de reference).
     *
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $events
     * @return array<int, string|null>
     */
    private function buildLunationKeysByEvent(array $events): array
    {
        $lunationNoByYear = [];
        $currentKey = null;
        $keys = [];

        foreach ($events as $index => $event) {
            $phase = (int) $event['phase'];
            $eventAt = $event['phase_hour']->setTimezone(new \DateTimeZone('UTC'));

            if ($phase === 0) {
                $year = (int) $eventAt->format('Y');
                $lunationNoByYear[$year] = ($lunationNoByYear[$year] ?? 0) + 1;
                $lunationNo = $lunationNoByYear[$year];

                $currentKey = sprintf('%02d_%s', $lunationNo, $eventAt->format('YmdHis'));
            }

            $keys[$index] = $currentKey;
        }

        return $keys;
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $anchors
     * @return array<int, string|null>
     */
    private function buildLunationKeysByAnchor(array $anchors): array
    {
        $lunationNoByYear = [];
        $currentKey = null;
        $keys = [];

        foreach ($anchors as $index => $event) {
            $phase = (int) $event['phase'];
            $eventAt = $event['phase_hour']->setTimezone(new \DateTimeZone('UTC'));

            if ($phase === 0) {
                $year = (int) $eventAt->format('Y');
                $lunationNoByYear[$year] = ($lunationNoByYear[$year] ?? 0) + 1;
                $lunationNo = $lunationNoByYear[$year];

                $currentKey = sprintf('%02d_%s', $lunationNo, $eventAt->format('YmdHis'));
            }

            $keys[$index] = $currentKey;
        }

        return $keys;
    }

    /**
     * @param array<int, array{phase:int, phase_hour:\DateTimeImmutable}> $events
     */
    private function countEventsInRange(
        array $events,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc
    ): int {
        $startTs = $startUtc->getTimestamp();
        $endTs = $endUtc->getTimestamp();
        $count = 0;

        foreach ($events as $event) {
            $ts = $event['phase_hour']->getTimestamp();
            if ($ts >= $startTs && $ts < $endTs) {
                $count++;
            }
        }

        return $count;
    }
}
