<?php

namespace App\Repository;

use App\Entity\OrbWindow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des fenetres orb_window.
 *
 * @extends ServiceEntityRepository<OrbWindow>
 */
class OrbWindowRepository extends ServiceEntityRepository
{
    private const SEARCH_PADDING_DAYS = 45;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrbWindow::class);
    }

    /**
     * Retourne la couverture mensuelle des fenetres deja calculees.
     *
     * @return string[]
     */
    public function findMonthCoverageByFamilyAndMethod(
        string $windowFamily,
        string $calculationMethod,
        int $startYear,
        int $endYear
    ): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            '
                SELECT DATE_FORMAT(event_at_utc, "%Y-%m") AS month_key
                FROM orb_window
                WHERE window_family = :family
                  AND calculation_method = :method
                  AND event_at_utc >= :start
                  AND event_at_utc < :end
                GROUP BY month_key
                ORDER BY month_key
            ',
            [
                'family' => $windowFamily,
                'method' => $calculationMethod,
                'start' => sprintf('%04d-01-01 00:00:00', $startYear),
                'end' => sprintf('%04d-01-01 00:00:00', $endYear + 1),
            ]
        );

        return array_values(array_filter(array_map(static fn ($v): string => (string) $v, $rows)));
    }

    /**
     * Supprime les fenetres existantes pour permettre un recalcul propre.
     */
    public function deleteByFamilyMethodAndEventRange(
        string $windowFamily,
        string $calculationMethod,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc
    ): int {
        return $this->getEntityManager()->getConnection()->executeStatement(
            '
                DELETE FROM orb_window
                WHERE window_family = :family
                  AND calculation_method = :method
                  AND event_at_utc >= :start
                  AND event_at_utc < :end
            ',
            [
                'family' => $windowFamily,
                'method' => $calculationMethod,
                'start' => $startUtc->format('Y-m-d H:i:s'),
                'end' => $endUtc->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Retourne les zones Orb Window qui chevauchent la fenetre timeline.
     * Requete optimisee via idx_orb_window_family_method_event (famille+methode+event_at_utc).
     *
     * @return array<int, array{
     *   id:int,
     *   phase_key:string,
     *   starts_at_utc:\DateTimeImmutable,
     *   ends_at_utc:\DateTimeImmutable,
     *   event_at_utc:\DateTimeImmutable,
     *   sequence_no:int|null,
     *   lunation_key:string|null
     * }>
     */
    public function findTimelineZonesByFamilyAndMethod(
        string $windowFamily,
        string $calculationMethod,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc
    ): array {
        $utc = new \DateTimeZone('UTC');
        $eventStart = $startUtc->modify(sprintf('-%d days', self::SEARCH_PADDING_DAYS));
        $eventEnd = $endUtc->modify(sprintf('+%d days', self::SEARCH_PADDING_DAYS));

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            '
                SELECT id, phase_key, event_at_utc, starts_at_utc, ends_at_utc, sequence_no, lunation_key
                FROM orb_window
                WHERE window_family = :family
                  AND calculation_method = :method
                  AND event_at_utc >= :event_start
                  AND event_at_utc < :event_end
                  AND ends_at_utc > :window_start
                  AND starts_at_utc < :window_end
                ORDER BY starts_at_utc ASC, ends_at_utc ASC, id ASC
            ',
            [
                'family' => $windowFamily,
                'method' => $calculationMethod,
                'event_start' => $eventStart->format('Y-m-d H:i:s'),
                'event_end' => $eventEnd->format('Y-m-d H:i:s'),
                'window_start' => $startUtc->format('Y-m-d H:i:s'),
                'window_end' => $endUtc->format('Y-m-d H:i:s'),
            ]
        );

        $zones = [];
        foreach ($rows as $row) {
            $phaseKey = trim((string) ($row['phase_key'] ?? ''));
            $startsRaw = trim((string) ($row['starts_at_utc'] ?? ''));
            $endsRaw = trim((string) ($row['ends_at_utc'] ?? ''));
            $eventRaw = trim((string) ($row['event_at_utc'] ?? ''));

            if ($phaseKey === '' || $startsRaw === '' || $endsRaw === '' || $eventRaw === '') {
                continue;
            }

            try {
                $startsAt = (new \DateTimeImmutable($startsRaw, $utc))->setTimezone($utc);
                $endsAt = (new \DateTimeImmutable($endsRaw, $utc))->setTimezone($utc);
                $eventAt = (new \DateTimeImmutable($eventRaw, $utc))->setTimezone($utc);
            } catch (\Throwable) {
                continue;
            }

            if ($endsAt <= $startsAt) {
                continue;
            }

            $zones[] = [
                'id' => (int) ($row['id'] ?? 0),
                'phase_key' => $phaseKey,
                'starts_at_utc' => $startsAt,
                'ends_at_utc' => $endsAt,
                'event_at_utc' => $eventAt,
                'sequence_no' => isset($row['sequence_no']) ? (int) $row['sequence_no'] : null,
                'lunation_key' => isset($row['lunation_key']) ? (string) $row['lunation_key'] : null,
            ];
        }

        return $zones;
    }
}
