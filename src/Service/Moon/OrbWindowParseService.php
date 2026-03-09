<?php

namespace App\Service\Moon;

use App\Entity\OrbWindow;
use App\Repository\MsMappingRepository;
use App\Repository\OrbWindowRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de parse vers orb_window.
 * Pourquoi: centraliser le calcul des fenetres temporelles autour des phases astronomiques exactes (phase_hour UTC).
 * Info: la methode "midpoint_partition_v1" decoupe par milieux entre evenements consecutifs.
 */
final class OrbWindowParseService
{
    public const FAMILY_INFLUENCE_ORB = 'influence_orb';
    public const METHOD_INFLUENCE_ORB = 'midpoint_partition_v1';

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
        $deleted = $orbWindowRepository->deleteByFamilyAndEventRange($family, $startUtc, $endUtc);
        if ($events === []) {
            return [
                'window_family' => $family,
                'calculation_method' => $method,
                'deleted' => $deleted,
                'created' => 0,
                'source_events' => 0,
            ];
        }

        $eventCountInRange = 0;
        foreach ($events as $event) {
            $ts = $event['phase_hour']->getTimestamp();
            if ($ts >= $startUtc->getTimestamp() && $ts < $endUtc->getTimestamp()) {
                $eventCountInRange++;
            }
        }

        $lunationKeysByEvent = $this->buildLunationKeysByEvent($events);

        $created = 0;
        $total = count($events);
        if ($total < 3) {
            return [
                'window_family' => $family,
                'calculation_method' => $method,
                'deleted' => $deleted,
                'created' => 0,
                'source_events' => $eventCountInRange,
            ];
        }

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

        $entityManager->flush();

        return [
            'window_family' => $family,
            'calculation_method' => $method,
            'deleted' => $deleted,
            'created' => $created,
            'source_events' => $eventCountInRange,
        ];
    }

    /**
     * @return string[]
     */
    public function supportedFamilies(): array
    {
        return [self::FAMILY_INFLUENCE_ORB];
    }

    public function resolveCalculationMethod(string $windowFamily): ?string
    {
        return match ($this->sanitizeFamily($windowFamily)) {
            self::FAMILY_INFLUENCE_ORB => self::METHOD_INFLUENCE_ORB,
            default => null,
        };
    }

    private function sanitizeFamily(string $windowFamily): string
    {
        return strtolower(trim($windowFamily));
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
}
