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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrbWindow::class);
    }

    /**
     * Retourne la couverture mensuelle des fenetres deja calculees.
     *
     * @return string[]
     */
    public function findMonthCoverageByFamily(string $windowFamily, int $startYear, int $endYear): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            '
                SELECT DATE_FORMAT(event_at_utc, "%Y-%m") AS month_key
                FROM orb_window
                WHERE window_family = :family
                  AND event_at_utc >= :start
                  AND event_at_utc < :end
                GROUP BY month_key
                ORDER BY month_key
            ',
            [
                'family' => $windowFamily,
                'start' => sprintf('%04d-01-01 00:00:00', $startYear),
                'end' => sprintf('%04d-01-01 00:00:00', $endYear + 1),
            ]
        );

        return array_values(array_filter(array_map(static fn ($v): string => (string) $v, $rows)));
    }

    /**
     * Supprime les fenetres existantes pour permettre un recalcul propre.
     */
    public function deleteByFamilyAndEventRange(
        string $windowFamily,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc
    ): int {
        return $this->getEntityManager()->getConnection()->executeStatement(
            '
                DELETE FROM orb_window
                WHERE window_family = :family
                  AND event_at_utc >= :start
                  AND event_at_utc < :end
            ',
            [
                'family' => $windowFamily,
                'start' => $startUtc->format('Y-m-d H:i:s'),
                'end' => $endUtc->format('Y-m-d H:i:s'),
            ]
        );
    }
}
