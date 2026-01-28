<?php

namespace App\Service\Ephemeris;

use Doctrine\DBAL\Connection;

final class EphemerisCoverageVerifierService
{
    private const ALLOWED_TABLES = [
        'moon_ephemeris_hour',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{
     *     label: string,
     *     table: string,
     *     rows: int,
     *     period_start: ?\DateTimeImmutable,
     *     period_end: ?\DateTimeImmutable,
     *     gaps: int,
     *     anomalies: string[]
     * }
     */
    public function verifyTable(string $table, string $label, int $stepSeconds, int $limit = 20): array
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new \InvalidArgumentException('Table invalide pour verification.');
        }
        if ($stepSeconds <= 0) {
            throw new \InvalidArgumentException('Pas attendu invalide.');
        }

        $rows = $this->connection->fetchFirstColumn(sprintf(
            'SELECT ts_utc FROM %s WHERE ts_utc IS NOT NULL ORDER BY ts_utc ASC',
            $table
        ));

        if (!$rows) {
            return [
                'label' => $label,
                'table' => $table,
                'rows' => 0,
                'period_start' => null,
                'period_end' => null,
                'gaps' => 0,
                'anomalies' => [],
            ];
        }

        $utc = new \DateTimeZone('UTC');
        $prev = null;
        $gaps = 0;
        $first = null;
        $last = null;
        $anomalies = [];

        foreach ($rows as $value) {
            $current = new \DateTimeImmutable((string) $value, $utc);
            if (!$first) {
                $first = $current;
            }
            $last = $current;

            if ($prev) {
                $diff = $current->getTimestamp() - $prev->getTimestamp();
                if ($diff > $stepSeconds) {
                    $missing = (int) floor($diff / $stepSeconds) - 1;
                    $gaps += max(1, $missing);
                    if (count($anomalies) < $limit) {
                        $anomalies[] = sprintf(
                            'Trou: %s -> %s (%ds)',
                            $prev->format('Y-m-d H:i'),
                            $current->format('Y-m-d H:i'),
                            $diff
                        );
                    }
                }
            }

            $prev = $current;
        }

        return [
            'label' => $label,
            'table' => $table,
            'rows' => count($rows),
            'period_start' => $first,
            'period_end' => $last,
            'gaps' => $gaps,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * @param array{
     *     label: string,
     *     table: string,
     *     rows: int,
     *     period_start: ?\DateTimeImmutable,
     *     period_end: ?\DateTimeImmutable,
     *     gaps: int,
     *     anomalies: string[]
     * } $result
     */
    public function formatReport(array $result): string
    {
        $lines = [];
        $lines[] = sprintf('%s (%s)', $result['label'], $result['table']);

        if ($result['rows'] === 0) {
            $lines[] = 'Aucune donnee.';
            return implode("\n", $lines);
        }

        $lines[] = sprintf('Lignes: %d', $result['rows']);
        $lines[] = sprintf(
            'Periode: %s -> %s',
            $this->formatDate($result['period_start']),
            $this->formatDate($result['period_end'])
        );
        $lines[] = sprintf('Trous: %d', $result['gaps']);

        if ($result['anomalies']) {
            $lines[] = 'Anomalies:';
            foreach ($result['anomalies'] as $anomaly) {
                $lines[] = ' - ' . $anomaly;
            }
        } else {
            $lines[] = 'Aucune anomalie detectee.';
        }

        return implode("\n", $lines);
    }

    private function formatDate(?\DateTimeInterface $date): string
    {
        if (!$date) {
            return '-';
        }

        return $date->format('Y-m-d H:i');
    }
}
