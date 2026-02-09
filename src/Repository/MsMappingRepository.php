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
