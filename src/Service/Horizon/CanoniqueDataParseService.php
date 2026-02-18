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
    private const BATCH_SIZE = 500;

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
        'm29_constellation',
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
     * @return array{processed:int, errors:int}
     */
    public function parseRun(
        ImportHorizon $run,
        string $prefix,
        \DateTimeZone $utc,
        ?callable $progressLogger = null
    ): array
    {
        $body = $run->getRawResponse();
        if ($body === null || trim($body) === '') {
            throw new \RuntimeException('Raw response vide.');
        }
        if (!str_contains($body, '$$SOE')) {
            throw new \RuntimeException('Reponse Horizons invalide: $$SOE introuvable.');
        }

        $stream = $this->parserService->streamResponse($body);
        $header = $stream['header'];
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

        $processed = 0;
        $errors = 0;
        $rawColumn = $prefix === 's' ? 's_raw_line' : 'm_raw_line';
        $columns = array_merge(['ts_utc', 'created_at_utc', $rawColumn], $map['columns']);
        $columnToIndex = array_flip($map['index_to_column']);
        $updateColumns = array_merge([$rawColumn], $map['columns']);
        $monthLabel = $this->resolveMonthLabel($run, $utc);
        $totalBatches = $this->resolveExpectedBatches($run);

        $batchCount = 0;
        $batchIndex = 0;
        $batchRows = [];
        $this->connection->beginTransaction();
        try {
            foreach ($stream['rows'] as $row) {
                $cols = $row['cols'] ?? [];
                if (!$cols) {
                    continue;
                }

                $timestampValue = $cols[$map['timestamp_index']] ?? $cols[0] ?? null;
                $timestamp = $this->dateTimeParser->parseHorizonsTimestamp($timestampValue, $utc);
                if (!$timestamp) {
                    $errors++;
                    continue;
                }

                $createdAt = (new \DateTime('now', $utc))->format('Y-m-d H:i:s');
                $rowValues = [
                    $timestamp->format('Y-m-d H:i:s'),
                    $createdAt,
                    $row['raw'] ?? null,
                ];
                foreach ($map['columns'] as $columnName) {
                    $index = $columnToIndex[$columnName] ?? null;
                    $rawValue = $index !== null ? ($cols[$index] ?? null) : null;
                    if (in_array($columnName, ['m20_range_km', 'm20_range_rate_km_s'], true)) {
                        $rowValues[] = $this->normalizeRawNumeric($rawValue);
                        continue;
                    }
                    if ($columnName === 'm29_constellation') {
                        $rowValues[] = $this->normalizeText($rawValue);
                        continue;
                    }
                    $rowValues[] = $this->normalizeNumeric($rawValue);
                }

                $batchRows[] = $rowValues;
                $batchCount++;
                $processed++;
                if ($batchCount >= self::BATCH_SIZE) {
                    $this->upsertBatch($columns, $updateColumns, $batchRows);
                    $this->connection->commit();
                    $this->connection->beginTransaction();
                    $batchIndex++;
                    if ($progressLogger) {
                        $progressLogger($this->formatProgressLog($monthLabel, $batchIndex, $totalBatches, $processed));
                    }
                    $batchCount = 0;
                    $batchRows = [];
                }
            }

            if ($batchRows) {
                $this->upsertBatch($columns, $updateColumns, $batchRows);
                $batchIndex++;
                if ($progressLogger) {
                    $progressLogger($this->formatProgressLog($monthLabel, $batchIndex, $totalBatches, $processed));
                }
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return ['processed' => $processed, 'errors' => $errors];
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

    private function normalizeText(?string $value): ?string
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

        return $clean;
    }

    private function normalizeRawNumeric(?string $value): ?string
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
     * @param string[] $columns
     * @param string[] $updateColumns
     * @param array<int, array<int, string|null>> $rows
     */
    private function upsertBatch(array $columns, array $updateColumns, array $rows): void
    {
        if (!$rows) {
            return;
        }

        $placeholdersPerRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($rows), $placeholdersPerRow));

        $values = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $values[] = $value;
            }
        }

        $platform = $this->connection->getDatabasePlatform();
        $quotedColumns = array_map([$platform, 'quoteIdentifier'], $columns);

        if ($platform instanceof PostgreSQLPlatform) {
            $updateAssignments = [];
            foreach ($updateColumns as $col) {
                $quoted = $platform->quoteIdentifier($col);
                $updateAssignments[] = $quoted . ' = EXCLUDED.' . $quoted;
            }

            $sql = sprintf(
                'INSERT INTO canonique_data (%s) VALUES %s ON CONFLICT (ts_utc) DO UPDATE SET %s',
                implode(', ', $quotedColumns),
                $placeholders,
                implode(', ', $updateAssignments)
            );
        } else {
            $updateAssignments = [];
            foreach ($updateColumns as $col) {
                $quoted = $platform->quoteIdentifier($col);
                $updateAssignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }

            $sql = sprintf(
                'INSERT INTO canonique_data (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
                implode(', ', $quotedColumns),
                $placeholders,
                implode(', ', $updateAssignments)
            );
        }

        $this->connection->executeStatement($sql, $values);
    }

    private function resolveMonthLabel(ImportHorizon $run, \DateTimeZone $utc): string
    {
        $start = $run->getStartUtc();
        if ($start) {
            return \DateTimeImmutable::createFromInterface($start)->setTimezone($utc)->format('M Y');
        }
        return 'Run';
    }

    private function resolveExpectedBatches(ImportHorizon $run): ?int
    {
        $start = $run->getStartUtc();
        $stop = $run->getStopUtc();
        $stepSeconds = $this->parseStepToSeconds((string) ($run->getStepSize() ?? ''));
        if (!$start || !$stop || $stepSeconds <= 0) {
            return null;
        }

        $startTs = \DateTimeImmutable::createFromInterface($start)->getTimestamp();
        $stopTs = \DateTimeImmutable::createFromInterface($stop)->getTimestamp();
        if ($stopTs < $startTs) {
            return null;
        }

        $expectedRows = intdiv($stopTs - $startTs, $stepSeconds) + 1;
        if ($expectedRows <= 0) {
            return null;
        }

        return (int) ceil($expectedRows / self::BATCH_SIZE);
    }

    private function formatProgressLog(string $label, int $batchIndex, ?int $totalBatches, int $processed): string
    {
        $total = $totalBatches ? (string) $totalBatches : '?';
        $memoryMb = (int) round(memory_get_usage(true) / 1024 / 1024);

        return sprintf(
            '[%s] batch %d/%s — rows: %d — memory: %dMB',
            $label,
            $batchIndex,
            $total,
            $processed,
            $memoryMb
        );
    }

    private function parseStepToSeconds(string $step): int
    {
        $value = trim($step);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/^(\d+)\s*([hmsd])$/i', $value, $matches) !== 1) {
            return 0;
        }

        $amount = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match ($unit) {
            'd' => $amount * 86400,
            'h' => $amount * 3600,
            'm' => $amount * 60,
            's' => $amount,
            default => 0,
        };
    }
}
