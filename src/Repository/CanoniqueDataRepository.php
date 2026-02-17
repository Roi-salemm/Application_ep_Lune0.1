<?php

/**
 * Acces SQL direct a la table canonique_data.
 * Pourquoi: schema fixe hors ORM, lecture admin en SQL brut.
 * Infos: retourne des tableaux associatifs pour l'affichage admin.
 */

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class CanoniqueDataRepository
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
            'SELECT * FROM canonique_data ORDER BY ts_utc DESC LIMIT :limit OFFSET :offset',
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
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM canonique_data');
    }

    /**
     * @return string[]
     */
    public function findMonthCoverage(): array
    {
        $rows = $this->connection->fetchFirstColumn('
            SELECT DATE_FORMAT(ts_utc, "%Y-%m") AS month_key
            FROM canonique_data
            WHERE ts_utc IS NOT NULL
            GROUP BY month_key
            ORDER BY month_key
        ');

        return array_values(array_filter($rows));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $value = $this->connection->fetchOne('SELECT MAX(ts_utc) FROM canonique_data');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }

    public function deleteByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM canonique_data WHERE ts_utc >= :start AND ts_utc < :stop',
            [
                'start' => $start->format('Y-m-d H:i:s'),
                'stop' => $stop->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->connection->fetchAllAssociative('
            SELECT
                ts_utc,
                m1_ra_ast_deg,
                m1_dec_ast_deg,
                m2_ra_app_deg,
                m2_dec_app_deg,
                m10_illum_frac,
                m20_range_km,
                m20_range_rate_km_s,
                m31_ecl_lon_deg,
                m31_ecl_lat_deg,
                m43_pab_lon_deg,
                m43_pab_lat_deg,
                m43_phi_deg,
                m29_constellation,
                s31_ecl_lon_deg,
                s31_ecl_lat_deg,
                created_at_utc
            FROM canonique_data
            WHERE ts_utc BETWEEN :start AND :end
            ORDER BY ts_utc ASC
        ', [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
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
                WHERE table_schema = 'public' AND table_name = 'canonique_data'
                ORDER BY ordinal_position
            ");
        } else {
            $rows = $this->connection->fetchFirstColumn('
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "canonique_data"
                ORDER BY ORDINAL_POSITION
            ');
        }

        return array_values(array_filter($rows));
    }
}
