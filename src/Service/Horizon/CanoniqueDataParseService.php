<?php

/**
 * Parse les imports Horizons pour alimenter canonique_data en valeurs brutes.
 * Pourquoi: centraliser le mapping des colonnes et garantir un stockage sans calcul.
 * Infos: mapping fixe sur le schema canonique (colonnes deja creees).
 */

namespace App\Service\Horizon;

use App\Entity\ImportHorizon;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class CanoniqueDataParseService
{
    /**
     * Ordre des colonnes attendues pour la Lune (hors timestamp).
     *
     * @var string[]
     */
    private const MOON_COLUMNS = [
        'm1_ra_ast_deg',
        'm1_dec_ast_deg',
        'm2_ra_app_deg',
        'm2_dec_app_deg',
        'm10_illum_frac',
        'm20_range_km',
        'm20_range_rate_km_s',
        'm31_ecl_lon_deg',
        'm31_ecl_lat_deg',
        'm43_pab_lon_deg',
        'm43_pab_lat_deg',
        'm43_phi_deg',
    ];

    /**
     * Ordre des colonnes attendues pour le Soleil (hors timestamp).
     *
     * @var string[]
     */
    private const SUN_COLUMNS = [
        's31_ecl_lon_deg',
        's31_ecl_lat_deg',
    ];

    public function __construct(
        private Connection $connection,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsDateTimeParserService $dateTimeParser,
    ) {
    }

    /**
     * @return array{saved:int, updated:int}
     */
    public function parseRun(ImportHorizon $run, string $prefix, \DateTimeZone $utc): array
    {
        $body = $run->getRawResponse();
        if ($body === null || trim($body) === '') {
            throw new \RuntimeException('Raw response vide.');
        }
        if (!str_contains($body, '$$SOE')) {
            throw new \RuntimeException('Reponse Horizons invalide: $$SOE introuvable.');
        }

        $parseResult = $this->parserService->parseResponse($body);
        $header = $parseResult->getHeader();
        if (!$header) {
            $rawHeader = $run->getRawHeader();
            if ($rawHeader !== null && trim($rawHeader) !== '') {
                $header = str_getcsv($rawHeader);
            }
        }
        if (!$header) {
            throw new \RuntimeException('Header CSV introuvable.');
        }

        $map = $this->buildColumnMap($header, $prefix);

        $saved = 0;
        $updated = 0;
        $rawColumn = $prefix === 's' ? 's_raw_line' : 'm_raw_line';

        foreach ($parseResult->getRows() as $row) {
            $cols = $row['cols'] ?? [];
            if (!$cols) {
                continue;
            }

            $timestampValue = $cols[$map['timestamp_index']] ?? $cols[0] ?? null;
            $timestamp = $this->dateTimeParser->parseHorizonsTimestamp($timestampValue, $utc);
            if (!$timestamp) {
                continue;
            }

            $data = [];
            foreach ($map['index_to_column'] as $index => $columnName) {
                $rawValue = $cols[$index] ?? null;
                $data[$columnName] = $this->normalizeNumeric($rawValue);
            }

            $result = $this->upsertRow($timestamp, $data, $row['raw'] ?? null, $rawColumn, $utc);
            if ($result === 'insert') {
                $saved++;
            } else {
                $updated++;
            }
        }

        return ['saved' => $saved, 'updated' => $updated];
    }

    /**
     * @param array<int, string> $header
     * @return array{
     *     timestamp_index:int,
     *     columns:array<int, string>,
     *     index_to_column:array<int, string>
     * }
     */
    private function buildColumnMap(array $header, string $prefix): array
    {
        $timestampIndex = $this->findTimestampIndex($header);
        $dataIndices = [];
        foreach ($header as $index => $label) {
            if ($index === $timestampIndex) {
                continue;
            }
            $labelText = trim((string) $label);
            if ($labelText === '') {
                continue;
            }
            $dataIndices[] = $index;
        }

        $expectedColumns = $this->resolveExpectedColumns($prefix);
        $expected = count($expectedColumns);
        if (count($dataIndices) !== $expected) {
            $preview = implode(', ', array_slice($header, 0, 6));
            throw new \RuntimeException(sprintf(
                'Nombre de colonnes inattendu (%d). Attendu: %d. Header: %s',
                count($dataIndices),
                $expected,
                $preview !== '' ? $preview : 'n/a'
            ));
        }

        $indexToColumn = [];
        foreach ($expectedColumns as $offset => $columnName) {
            $headerIndex = $dataIndices[$offset];
            $indexToColumn[$headerIndex] = $columnName;
        }

        return [
            'timestamp_index' => $timestampIndex,
            'columns' => $expectedColumns,
            'index_to_column' => $indexToColumn,
        ];
    }

    /**
     * @param array<int, string> $header
     */
    private function findTimestampIndex(array $header): int
    {
        foreach ($header as $index => $label) {
            $text = trim((string) $label);
            if ($text === '') {
                continue;
            }
            if (preg_match('/Date__\(UT\)__HR:MN|Date__\(UT\)|\bDATE\b|\bTIME\b/i', $text) === 1) {
                return (int) $index;
            }
        }

        return 0;
    }

    private function normalizeNumeric(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $lower = strtolower($clean);
        if (in_array($lower, ['n.a.', 'na', 'n/a', '*', '-'], true)) {
            return null;
        }

        $clean = str_replace(['km', 'KM', 'deg', 'DEG', 'au', 'AU'], '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B\"'");
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        return $clean;
    }

    /**
     * @return string[]
     */
    private function resolveExpectedColumns(string $prefix): array
    {
        return match ($prefix) {
            'm' => self::MOON_COLUMNS,
            's' => self::SUN_COLUMNS,
            default => throw new \RuntimeException('Prefixe invalide pour le parse canonique.'),
        };
    }

    /**
     * @param array<string, string|null> $data
     * @return "insert"|"update"
     */
    private function upsertRow(
        \DateTimeInterface $timestamp,
        array $data,
        ?string $rawLine,
        string $rawColumn,
        \DateTimeZone $utc
    ): string {
        $tsValue = $timestamp->format('Y-m-d H:i:s');
        $createdAt = (new \DateTime('now', $utc))->format('Y-m-d H:i:s');

        $columns = array_merge(['ts_utc', 'created_at_utc', $rawColumn], array_keys($data));
        $values = array_merge([$tsValue, $createdAt, $rawLine], array_values($data));
        $placeholders = array_fill(0, count($columns), '?');
        $quotedColumns = array_map([$this->connection->getDatabasePlatform(), 'quoteIdentifier'], $columns);

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $updateAssignments = [];
            foreach (array_merge([$rawColumn], array_keys($data)) as $col) {
                $quoted = $this->connection->getDatabasePlatform()->quoteIdentifier($col);
                $updateAssignments[] = $quoted . ' = EXCLUDED.' . $quoted;
            }

            $sql = sprintf(
                'INSERT INTO canonique_data (%s) VALUES (%s) ON CONFLICT (ts_utc) DO UPDATE SET %s',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updateAssignments)
            );
        } else {
            $updateAssignments = [];
            foreach (array_merge([$rawColumn], array_keys($data)) as $col) {
                $quoted = $this->connection->getDatabasePlatform()->quoteIdentifier($col);
                $updateAssignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }

            $sql = sprintf(
                'INSERT INTO canonique_data (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updateAssignments)
            );
        }

        $exists = (bool) $this->connection->fetchOne(
            'SELECT ts_utc FROM canonique_data WHERE ts_utc = ?',
            [$tsValue]
        );

        $this->connection->executeStatement($sql, $values);

        return $exists ? 'update' : 'insert';
    }
}
