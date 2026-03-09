<?php

/**
 * Repository Doctrine pour la table ms_mapping.
 * Pourquoi: conserver la logique existante tout en typant via ORM.
 */

namespace App\Repository;

use App\Entity\MsMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Persistence\ManagerRegistry;

final class MsMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MsMapping::class);
    }

    private function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findLatestPaged(int $limit, int $offset): array
    {
        return $this->connection()->fetchAllAssociative(
            'SELECT * FROM ms_mapping ORDER BY ts_utc ASC LIMIT :limit OFFSET :offset',
            [
                'limit' => $limit,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ]
        );
    }

    public function countAll(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ms_mapping');
    }

    /**
     * @return string[]
     */
    public function findMonthCoverage(): array
    {
        $rows = $this->connection()->fetchFirstColumn('
            SELECT DATE_FORMAT(ts_utc, "%Y-%m") AS month_key
            FROM ms_mapping
            WHERE ts_utc IS NOT NULL
            GROUP BY month_key
            ORDER BY month_key
        ');

        return array_values(array_filter($rows));
    }

    /**
     * Retourne les mois qui possedent des evenements astronomiques exploitables.
     * Pourquoi: le parse orb_window ne depend que des phases avec phase_hour renseigne.
     *
     * @return string[]
     */
    public function findPhaseEventMonthCoverage(int $startYear, int $endYear): array
    {
        $rows = $this->connection()->fetchFirstColumn(
            '
                SELECT DATE_FORMAT(phase_hour, "%Y-%m") AS month_key
                FROM ms_mapping
                WHERE phase BETWEEN 0 AND 7
                  AND phase_hour IS NOT NULL
                  AND phase_hour >= :start
                  AND phase_hour < :end
                GROUP BY month_key
                ORDER BY month_key
            ',
            [
                'start' => sprintf('%04d-01-01 00:00:00', $startYear),
                'end' => sprintf('%04d-01-01 00:00:00', $endYear + 1),
            ]
        );

        return array_values(array_filter(array_map(static fn ($v): string => (string) $v, $rows)));
    }

    /**
     * @return array<int, array{phase:int, phase_hour:\DateTimeImmutable}>
     */
    public function findPhaseEventsByPhaseHourRange(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc
    ): array {
        $rows = $this->connection()->fetchAllAssociative(
            '
                SELECT phase, phase_hour
                FROM ms_mapping
                WHERE phase BETWEEN 0 AND 7
                  AND phase_hour IS NOT NULL
                  AND phase_hour >= :start
                  AND phase_hour < :end
                ORDER BY phase_hour ASC
            ',
            [
                'start' => $startUtc->format('Y-m-d H:i:s'),
                'end' => $endUtc->format('Y-m-d H:i:s'),
            ]
        );

        $utc = new \DateTimeZone('UTC');
        $events = [];
        $seenBySecond = [];
        foreach ($rows as $row) {
            $phase = isset($row['phase']) ? (int) $row['phase'] : -1;
            $raw = isset($row['phase_hour']) ? trim((string) $row['phase_hour']) : '';
            if ($phase < 0 || $phase > 7 || $raw === '') {
                continue;
            }
            try {
                $phaseHour = (new \DateTimeImmutable($raw, $utc))->setTimezone($utc);
            } catch (\Throwable) {
                continue;
            }

            $key = $phaseHour->format('Y-m-d H:i:s');
            if (isset($seenBySecond[$key])) {
                continue;
            }
            $seenBySecond[$key] = true;
            $events[] = [
                'phase' => $phase,
                'phase_hour' => $phaseHour,
            ];
        }

        return $events;
    }

    /**
     * @return MsMapping[]
     */
    public function findByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.ts_utc BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('m.ts_utc', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    public function fetchColumnNames(): array
    {
        $columns = $this->getEntityManager()
            ->getClassMetadata(MsMapping::class)
            ->getColumnNames();

        return array_values(array_filter($columns));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $value = $this->connection()->fetchOne('SELECT MAX(ts_utc) FROM ms_mapping');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }

    public function deleteByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): int
    {
        return $this->connection()->executeStatement(
            'DELETE FROM ms_mapping WHERE ts_utc >= :start AND ts_utc < :stop',
            [
                'start' => $start->format('Y-m-d H:i:s'),
                'stop' => $stop->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<int, array{phase:int, phase_hour:\DateTimeImmutable}>
     */
    public function findPhaseEventsForTimeline(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        int $paddingHours = 96
    ): array {
        $from = $startUtc->modify(sprintf('-%d hours', max(0, $paddingHours)));
        $to = $endUtc->modify(sprintf('+%d hours', max(0, $paddingHours)));
        $utc = new \DateTimeZone('UTC');

        $rows = $this->connection()->fetchAllAssociative(
            '
                SELECT phase, phase_hour, ts_utc
                FROM ms_mapping
                WHERE phase IS NOT NULL
                  AND phase BETWEEN 0 AND 7
                  AND phase_hour IS NOT NULL
                  AND DATE(phase_hour) = DATE(ts_utc)
                  AND phase_hour >= :from
                  AND phase_hour <= :to
                ORDER BY phase_hour ASC, ts_utc ASC
            ',
            [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ]
        );

        // Plusieurs lignes horaires peuvent partager le meme phase_hour.
        // On conserve pour chaque instant l entree la plus proche de phase_hour.
        $grouped = [];
        foreach ($rows as $row) {
            $phase = isset($row['phase']) ? (int) $row['phase'] : -1;
            $phaseHourRaw = isset($row['phase_hour']) ? trim((string) $row['phase_hour']) : '';
            if ($phase < 0 || $phase > 7 || $phaseHourRaw === '') {
                continue;
            }

            try {
                $phaseHour = new \DateTimeImmutable($phaseHourRaw, $utc);
            } catch (\Throwable) {
                continue;
            }

            $groupKey = $phaseHour->format('Y-m-d H:i:s');
            $tsUtcRaw = isset($row['ts_utc']) ? trim((string) $row['ts_utc']) : '';
            $distance = PHP_INT_MAX;
            if ($tsUtcRaw !== '') {
                try {
                    $tsUtc = new \DateTimeImmutable($tsUtcRaw, $utc);
                    $distance = abs($tsUtc->getTimestamp() - $phaseHour->getTimestamp());
                } catch (\Throwable) {
                    $distance = PHP_INT_MAX;
                }
            }

            if (!isset($grouped[$groupKey]) || $distance < $grouped[$groupKey]['distance']) {
                $grouped[$groupKey] = [
                    'phase' => $phase,
                    'phase_hour' => $phaseHour,
                    'distance' => $distance,
                ];
            }
        }

        $events = array_values(array_map(
            static fn (array $row): array => [
                'phase' => (int) $row['phase'],
                'phase_hour' => $row['phase_hour'],
            ],
            $grouped
        ));

        usort(
            $events,
            static fn (array $a, array $b): int =>
                $a['phase_hour']->getTimestamp() <=> $b['phase_hour']->getTimestamp()
        );

        return $events;
    }

    /**
     * @param array<string, mixed> $data
     * @return "insert"|"update"
     */
    public function upsertRow(array $data): string
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');
        $connection = $this->connection();
        $quotedColumns = array_map([$connection->getDatabasePlatform(), 'quoteIdentifier'], $columns);

        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $updateAssignments = [];
            foreach ($columns as $column) {
                if ($column === 'ts_utc') {
                    continue;
                }
                $quoted = $connection->getDatabasePlatform()->quoteIdentifier($column);
                $updateAssignments[] = $quoted . ' = EXCLUDED.' . $quoted;
            }

            $sql = sprintf(
                'INSERT INTO ms_mapping (%s) VALUES (%s) ON CONFLICT (ts_utc) DO UPDATE SET %s',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updateAssignments)
            );
        } else {
            $updateAssignments = [];
            foreach ($columns as $column) {
                if ($column === 'ts_utc') {
                    continue;
                }
                $quoted = $connection->getDatabasePlatform()->quoteIdentifier($column);
                $updateAssignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }

            $sql = sprintf(
                'INSERT INTO ms_mapping (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updateAssignments)
            );
        }

        $exists = (bool) $connection->fetchOne(
            'SELECT id FROM ms_mapping WHERE ts_utc = ?',
            [$data['ts_utc'] ?? null]
        );

        $connection->executeStatement($sql, $values);

        return $exists ? 'update' : 'insert';
    }
}
