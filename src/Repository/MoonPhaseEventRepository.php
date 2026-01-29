<?php

namespace App\Repository;

use App\Entity\MoonPhaseEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MoonPhaseEvent>
 */
class MoonPhaseEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoonPhaseEvent::class);
    }

    /**
     * @return MoonPhaseEvent[]
     */
    public function findLatest(int $limit): array
    {
        return $this->findBy([], ['ts_utc' => 'DESC'], $limit);
    }

    /**
     * @return MoonPhaseEvent[]
     */
    public function findByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        $qb = $this->createQueryBuilder('e');
        $expr = $qb->expr();

        $qb->andWhere(
            $expr->orX(
                $expr->andX(
                    $expr->isNotNull('e.display_at_utc'),
                    $expr->gte('e.display_at_utc', ':start'),
                    $expr->lte('e.display_at_utc', ':stop')
                ),
                $expr->andX(
                    $expr->isNull('e.display_at_utc'),
                    $expr->gte('e.ts_utc', ':start'),
                    $expr->lte('e.ts_utc', ':stop')
                )
            )
        )
        ->setParameter('start', $start)
        ->setParameter('stop', $stop)
        ->orderBy('e.display_at_utc', 'ASC')
        ->addOrderBy('e.ts_utc', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, MoonPhaseEvent>
     */
    public function findByTimestampRangeIndexed(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.ts_utc >= :start')
            ->andWhere('e.ts_utc <= :stop')
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $ts = $row->getTsUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }
            $key = $row->getEventType() . '|' . $ts->format('Y-m-d H:i');
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @return string[]
     */
    public function findMonthCoverage(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            $dateExpr = "TO_CHAR(COALESCE(display_at_utc, ts_utc), 'YYYY-MM')";
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $dateExpr = "strftime('%Y-%m', COALESCE(display_at_utc, ts_utc))";
        } else {
            $dateExpr = 'DATE_FORMAT(COALESCE(display_at_utc, ts_utc), "%Y-%m")';
        }

        $rows = $connection->fetchFirstColumn(sprintf('
            SELECT %s AS month_key
            FROM moon_phase_event
            WHERE COALESCE(display_at_utc, ts_utc) IS NOT NULL
            GROUP BY month_key
            ORDER BY month_key
        ', $dateExpr));

        $clean = array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $rows);

        return array_values(array_filter($clean));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $connection = $this->getEntityManager()->getConnection();
        $value = $connection->fetchOne('SELECT MAX(COALESCE(display_at_utc, ts_utc)) FROM moon_phase_event');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }
}
