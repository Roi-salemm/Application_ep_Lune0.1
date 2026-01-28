<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Repository\MoonEphemerisHourRepository;
use App\Service\Moon\Horizons\MoonHorizonsImportService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:ephemeris:bulk-import',
    description: 'Import Moon ephemeris data month by month.',
)]
class BulkImportEphemerisCommand extends Command
{
    public function __construct(
        private MoonHorizonsClientService $clientService,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsImportService $moonImportService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
        private MoonEphemerisHourRepository $moonRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start month (UTC). Example: 2026-01-01', 'now')
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Number of months to import', '1')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Horizons step size', '1h')
            ->addOption('moon-target', null, InputOption::VALUE_OPTIONAL, 'Moon target body', '301')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('moon-quantities', null, InputOption::VALUE_OPTIONAL, 'Moon quantities list', '1,10,13,14,15,17,20,23,24,29,30,31,49')
            ->addOption('time-zone', null, InputOption::VALUE_OPTIONAL, 'Time zone label stored in the run', 'UTC')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and parse without saving to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');
        $startInput = (string) $input->getOption('start');
        $months = max(1, (int) $input->getOption('months'));
        $step = trim((string) $input->getOption('step')) ?: '1h';
        $moonTarget = trim((string) $input->getOption('moon-target')) ?: '301';
        $center = trim((string) $input->getOption('center')) ?: '500@399';
        $moonQuantities = trim((string) $input->getOption('moon-quantities')) ?: '1,10,13,14,15,17,20,23,24,29,30,31,49';
        $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

        $start = $this->dateTimeParserService->parseInput($startInput, $utc);
        if (!$start) {
            $output->writeln('<error>Invalid --start value.</error>');
            return Command::FAILURE;
        }

        $start = (clone $start)->setTimezone($utc)->modify('first day of this month')->setTime(0, 0, 0);
        $stepSeconds = $this->parseStepToSeconds($step);
        if ($stepSeconds <= 0) {
            $stepSeconds = 3600;
        }

        for ($index = 0; $index < $months; $index++) {
            $chunkStart = (clone $start)->modify(sprintf('+%d months', $index));
            $nextMonthStart = (clone $chunkStart)->modify('+1 month');
            $chunkStop = (clone $nextMonthStart)->modify(sprintf('-%d seconds', $stepSeconds));

            $output->writeln(sprintf(
                'Import %s -> %s (UTC)',
                $chunkStart->format('Y-m-d H:i'),
                $chunkStop->format('Y-m-d H:i')
            ));

            try {
                $moonResponse = $this->clientService->fetchEphemeris(
                    $chunkStart,
                    $chunkStop,
                    $moonTarget,
                    $center,
                    $step,
                    $moonQuantities
                );
            } catch (\RuntimeException $e) {
                $output->writeln('<error>Moon fetch failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            if ($moonResponse['status'] >= 400) {
                $output->writeln('<error>Moon Horizons HTTP ' . $moonResponse['status'] . '.</error>');
                return Command::FAILURE;
            }

            $moonParse = $this->parserService->parseResponse($moonResponse['body']);

            if ($dryRun) {
                $output->writeln(sprintf('Moon rows: %d', count($moonParse->getRows())));
            } else {
                $moonRun = $this->moonImportService->createRun(
                    $moonTarget,
                    $center,
                    $chunkStart,
                    $chunkStop,
                    $step,
                    $timeZoneLabel,
                    $moonResponse['body'],
                    false,
                    $utc
                );
                $moonResult = $this->moonImportService->importParsedRows($moonRun, $moonParse, $utc);
                $output->writeln(sprintf('Moon saved %d, updated %d.', $moonResult['saved'], $moonResult['updated']));
            }
            if ($dryRun) {
                $this->fetchSunGeocentric($chunkStart, $chunkStop, $step, $utc, $output, true);
                continue;
            }

            $result = $this->fetchSunGeocentric($chunkStart, $chunkStop, $step, $utc, $output, false);
            if ($result === null) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function fetchSunGeocentric(
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        string $step,
        \DateTimeZone $utc,
        OutputInterface $output,
        bool $dryRun
    ): ?int {
        $output->writeln('<comment>Fetch Sun geocentric RA/DEC + ecliptic lon/lat (target=10, center=500@399, quantities=1,20,31).</comment>');

        try {
            $response = $this->clientService->fetchEphemeris($start, $stop, '10', '500@399', $step, '1,20,31');
        } catch (\RuntimeException $e) {
            $output->writeln('<error>Sun geocentric fetch failed: ' . $e->getMessage() . '</error>');
            return null;
        }

        if ($response['status'] >= 400) {
            $output->writeln('<error>Sun geocentric Horizons HTTP ' . $response['status'] . '.</error>');
            return null;
        }

        $parse = $this->parserService->parseResponse($response['body']);
        $columnMap = $parse->getColumnMap();
        $raIndex = $columnMap['ra_hours'] ?? null;
        $decIndex = $columnMap['dec_deg'] ?? null;
        $lonIndex = $columnMap['elon_deg'] ?? null;
        $latIndex = $columnMap['elat_deg'] ?? null;

        if ($raIndex === null || $decIndex === null) {
            $output->writeln('<error>Sun RA/DEC columns not found in Horizons response.</error>');
            return null;
        }

        if ($lonIndex === null || $latIndex === null) {
            $output->writeln('<error>Sun ecliptic lon/lat columns not found in Horizons response.</error>');
            return null;
        }

        if ($dryRun) {
            $output->writeln(sprintf('Sun geocentric rows: %d', count($parse->getRows())));
            return 0;
        }

        $moonRows = $this->moonRepository->findByTimestampRangeIndexed($start, $stop);
        if (!$moonRows) {
            $output->writeln('<comment>No moon rows found for sun geocentric update.</comment>');
            return 0;
        }

        $updated = 0;
        foreach ($parse->getRows() as $row) {
            $timestamp = $this->parseTimestamp($row, $columnMap, $utc);
            if (!$timestamp) {
                continue;
            }
            $timestampKey = $timestamp->format('Y-m-d H:i');
            if (!isset($moonRows[$timestampKey])) {
                continue;
            }

            $cols = $row['cols'] ?? [];
            $ra = $this->parseRaHours($this->extractColumnValue($cols, $raIndex));
            $dec = $this->parseDecDegrees($this->extractColumnValue($cols, $decIndex));
            $sunLon = $this->parseNumeric($this->extractColumnValue($cols, $lonIndex));
            $sunLat = $this->parseNumeric($this->extractColumnValue($cols, $latIndex));
            $sunDistAu = $this->parseSunDistanceAu($cols, $columnMap);

            $moon = $moonRows[$timestampKey];
            if ($ra !== null) {
                $moon->setSunRaHours($this->formatDecimal($ra, 6));
            }
            if ($dec !== null) {
                $moon->setSunDecDeg($this->formatDecimal($dec, 6));
            }
            if ($sunLon !== null) {
                $moon->setSunEclLonDeg($this->formatDecimal($sunLon, 6));
            }
            if ($sunLat !== null) {
                $moon->setSunEclLatDeg($this->formatDecimal($sunLat, 6));
            }
            if ($sunDistAu !== null) {
                $moon->setSunDistAu($this->formatDecimal($sunDistAu, 14));
            }
            $updated++;
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('Sun geocentric updated on %d moon rows.', $updated));

        return $updated;
    }

    private function parseTimestamp(array $row, array $columnMap, \DateTimeZone $utc): ?\DateTime
    {
        $cols = $row['cols'] ?? [];
        $timestampValue = $this->extractColumnValue($cols, $columnMap['timestamp'] ?? null);
        if ($timestampValue === null && $cols) {
            $timestampValue = $cols[0];
        }

        return $this->dateTimeParserService->parseHorizonsTimestamp($timestampValue, $utc);
    }

    private function extractColumnValue(array $cols, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return $cols[$index] ?? null;
    }

    private function parseNumeric(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '' || strtolower($clean) === 'n.a.' || strtolower($clean) === 'na') {
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

        return (float) $clean;
    }

    private function parseRaHours(?string $value): ?float
    {
        $decimal = $this->parseNumeric($value);
        if ($decimal !== null) {
            return $decimal;
        }

        return $this->parseHmsToDecimal($value);
    }

    private function parseDecDegrees(?string $value): ?float
    {
        $decimal = $this->parseNumeric($value);
        if ($decimal !== null) {
            return $decimal;
        }

        return $this->parseDmsToDecimal($value);
    }

    private function parseHmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['h', 'm', 's'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $hours = (float) $parts[0];
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $hours + ($minutes / 60.0) + ($seconds / 3600.0);
    }

    private function parseDmsToDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        $clean = str_replace(['d', "'", '"'], ' ', $clean);
        $parts = preg_split('/[:\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) {
            return null;
        }

        $sign = 1.0;
        if (str_starts_with($parts[0], '-')) {
            $sign = -1.0;
        }

        $degrees = abs((float) $parts[0]);
        $minutes = isset($parts[1]) ? (float) $parts[1] : 0.0;
        $seconds = isset($parts[2]) ? (float) $parts[2] : 0.0;

        return $sign * ($degrees + ($minutes / 60.0) + ($seconds / 3600.0));
    }

    private function parseSunDistanceAu(array $cols, array $columnMap): ?float
    {
        $deltaIndex = $columnMap['delta_au'] ?? null;
        $distIndex = $columnMap['dist_km'] ?? null;
        $raw = null;

        if ($deltaIndex !== null) {
            $raw = $this->extractColumnValue($cols, $deltaIndex);
        }
        if ($raw === null && $distIndex !== null) {
            $raw = $this->extractColumnValue($cols, $distIndex);
        }

        $value = $this->parseNumeric($raw);
        if ($value === null) {
            return null;
        }

        if ($value > 1000.0) {
            return $value / 149597870.7;
        }

        return $value;
    }

    private function formatDecimal(float $value, int $scale): string
    {
        $formatted = sprintf('%.' . $scale . 'f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
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
