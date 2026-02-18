<?php

/**
 * Repository DBAL pour la table month_parse_coverage.
 * Pourquoi: centraliser l'etat des mois parses sans scanner les grosses tables.
 * Infos: une ligne par mois et par table cible (canonique_data, ms_mapping).
 */

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class MonthParseCoverageRepository
{
    public const TARGET_CANONIQUE_DATA = 'canonique_data';
    public const TARGET_MS_MAPPING = 'ms_mapping';
    public const STATUS_PARSED = 'parsed';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return string[]
     */
    public function findMonthCoverage(string $targetTable, ?string $status = self::STATUS_PARSED): array
    {
        $sql = 'SELECT month_key FROM month_parse_coverage WHERE target_table = :target';
        $params = ['target' => $targetTable];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY month_key';

        $rows = $this->connection->fetchFirstColumn($sql, $params);

        return array_values(array_filter($rows));
    }

    public function upsertMonthStatus(
        string $targetTable,
        string $monthKey,
        string $status,
        \DateTimeInterface $now
    ): void {
        $createdAt = $now->format('Y-m-d H:i:s');
        $updatedAt = $createdAt;

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $sql = '
                INSERT INTO month_parse_coverage (target_table, month_key, status, created_at_utc, updated_at_utc)
                VALUES (:target, :month, :status, :created_at, :updated_at)
                ON CONFLICT (target_table, month_key) DO UPDATE
                SET status = EXCLUDED.status,
                    updated_at_utc = EXCLUDED.updated_at_utc
            ';
        } else {
            $sql = '
                INSERT INTO month_parse_coverage (target_table, month_key, status, created_at_utc, updated_at_utc)
                VALUES (:target, :month, :status, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    updated_at_utc = VALUES(updated_at_utc)
            ';
        }

        $this->connection->executeStatement($sql, [
            'target' => $targetTable,
            'month' => $monthKey,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }

    public function deleteMonth(string $targetTable, string $monthKey): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM month_parse_coverage WHERE target_table = :target AND month_key = :month',
            [
                'target' => $targetTable,
                'month' => $monthKey,
            ]
        );
    }

    public function deleteYear(string $targetTable, int $year): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM month_parse_coverage WHERE target_table = :target AND month_key LIKE :prefix',
            [
                'target' => $targetTable,
                'prefix' => sprintf('%04d-%%', $year),
            ]
        );
    }

    public function deleteAllForTarget(string $targetTable): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM month_parse_coverage WHERE target_table = :target',
            [
                'target' => $targetTable,
            ]
        );
    }
}
