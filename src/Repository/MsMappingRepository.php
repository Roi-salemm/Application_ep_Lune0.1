<?php

/**
 * Acces SQL direct a la table ms_mapping.
 * Pourquoi: schema simple, lecture admin et upsert mensuel.
 */

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class MsMappingRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findLatestPaged(int $limit, int $offset): array
    {
        return $this->connection->fetchAllAssociative(
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
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ms_mapping');
    }

    /**
     * @return string[]
     */
    public function fetchColumnNames(): array
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $rows = $this->connection->fetchFirstColumn("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'ms_mapping'
                ORDER BY ordinal_position
            ");
        } else {
            $rows = $this->connection->fetchFirstColumn('
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "ms_mapping"
                ORDER BY ORDINAL_POSITION
            ');
        }

        return array_values(array_filter($rows));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $value = $this->connection->fetchOne('SELECT MAX(ts_utc) FROM ms_mapping');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }

    public function deleteByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): int
    {
        return $this->connection->executeStatement(
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
        $quotedColumns = array_map([$this->connection->getDatabasePlatform(), 'quoteIdentifier'], $columns);

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $updateAssignments = [];
            foreach ($columns as $column) {
                if ($column === 'ts_utc') {
                    continue;
                }
                $quoted = $this->connection->getDatabasePlatform()->quoteIdentifier($column);
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
                $quoted = $this->connection->getDatabasePlatform()->quoteIdentifier($column);
                $updateAssignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }

            $sql = sprintf(
                'INSERT INTO ms_mapping (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updateAssignments)
            );
        }

        $exists = (bool) $this->connection->fetchOne(
            'SELECT id FROM ms_mapping WHERE ts_utc = ?',
            [$data['ts_utc'] ?? null]
        );

        $this->connection->executeStatement($sql, $values);

        return $exists ? 'update' : 'insert';
    }
}
