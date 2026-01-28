<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsImportService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use App\Repository\MoonEphemerisHourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:moon:import-horizons',
    description: 'Import Moon ephemeris data from NASA JPL Horizons.',
)]
class ImportMoonHorizonsCommand extends Command
{
    public function __construct(
        private MoonHorizonsClientService $clientService,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsImportService $importService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
        private MoonEphemerisHourRepository $moonRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start time (UTC). Example: 2026-01-01 00:00', 'now')
            ->addOption('stop', null, InputOption::VALUE_OPTIONAL, 'Stop time (UTC). Example: 2026-01-08 00:00')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to import when stop is not provided', '7')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Horizons step size', '1h')
            ->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target body (301 = Moon)', '301')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('quantities', null, InputOption::VALUE_OPTIONAL, 'Horizons quantities list', '1,10,13,14,15,17,20,23,24,29,30,31,49')
            ->addOption('skip-sun', null, InputOption::VALUE_NONE, 'Skip fetching Sun geocentric data')
            ->addOption('time-zone', null, InputOption::VALUE_OPTIONAL, 'Time zone label stored in the run', 'UTC')
            ->addOption('show-columns', null, InputOption::VALUE_NONE, 'Print parsed column mapping')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and parse without saving to the database')
            ->addOption('store-only', null, InputOption::VALUE_NONE, 'Store the raw response only')
            ->addOption('run-id', null, InputOption::VALUE_OPTIONAL, 'Parse an existing import run by id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');
        $storeOnly = (bool) $input->getOption('store-only');
        $runIdOption = $input->getOption('run-id');

        $run = null;
        $body = null;
        $start = null;
        $stop = null;
        $target = null;
        $center = null;
        $step = null;
        $quantities = null;
        $timeZoneLabel = null;
        $skipSun = (bool) $input->getOption('skip-sun');

        if ($runIdOption !== null) {
            $run = $this->importService->findRunById((int) $runIdOption);
            if (!$run) {
                $output->writeln('<error>Run id not found.</error>');
                return Command::FAILURE;
            }

            $body = $run->getRawResponse();
            if (!$body) {
                $output->writeln('<error>Selected run has no raw response.</error>');
                return Command::FAILURE;
            }

            $start = $run->getStartUtc() ?? new \DateTime('now', $utc);
            $stop = $run->getStopUtc() ?? (clone $start);
            $center = $run->getCenter();
            $step = $run->getStepSize();
            $output->writeln(sprintf('Parsing stored response for run id %d...', $run->getId()));
        } else {
            $start = $this->dateTimeParserService->parseInput((string) $input->getOption('start'), $utc);
            if (!$start) {
                $output->writeln('<error>Invalid --start value.</error>');
                return Command::FAILURE;
            }

            $stopOption = $input->getOption('stop');
            if ($stopOption !== null) {
                $stop = $this->dateTimeParserService->parseInput((string) $stopOption, $utc);
                if (!$stop) {
                    $output->writeln('<error>Invalid --stop value.</error>');
                    return Command::FAILURE;
                }
            } else {
                $days = max(1, (int) $input->getOption('days'));
                $stop = (clone $start)->modify(sprintf('+%d days', $days));
            }

            if ($stop <= $start) {
                $output->writeln('<error>Stop time must be after start time.</error>');
                return Command::FAILURE;
            }

            $target = trim((string) $input->getOption('target'));
            $center = trim((string) $input->getOption('center'));
            $step = trim((string) $input->getOption('step'));
            $quantities = trim((string) $input->getOption('quantities'));
            $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

            $output->writeln('Requesting ephemeris from NASA JPL Horizons...');

            try {
                $response = $this->clientService->fetchEphemeris($start, $stop, $target, $center, $step, $quantities);
                $status = $response['status'];
                $body = $response['body'];
            } catch (\RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            if ($status >= 400) {
                $output->writeln('<error>Horizons returned HTTP ' . $status . '.</error>');
                return Command::FAILURE;
            }
        }

        $parseResult = $this->parserService->parseResponse($body);

        if ($input->getOption('show-columns')) {
            $header = $parseResult->getHeader();
            $rows = $parseResult->getRows();
            $headerLine = $parseResult->getHeaderLine();

            if ($header) {
                $output->writeln('Columns: ' . implode(' | ', $header));
            } elseif ($rows) {
                $output->writeln('Columns: <no header found>');
                $firstRow = $rows[0]['cols'] ?? [];
                $output->writeln('First data row: ' . implode(' | ', $firstRow));
            } else {
                $output->writeln('Columns: <no header or data found>');
            }

            if ($headerLine && !$header) {
                $output->writeln('Header candidate: ' . $headerLine);
            }

            $output->writeln('Mapping: ' . $this->parserService->formatColumnMap($parseResult->getColumnMap()));
        }

        if ($dryRun) {
            $output->writeln(sprintf('Parsed rows: %d', count($parseResult->getRows())));
            return Command::SUCCESS;
        }

        if ($run === null) {
            $run = $this->importService->createRun(
                $target,
                $center,
                $start,
                $stop,
                $step,
                $timeZoneLabel,
                $body,
                $storeOnly,
                $utc
            );

            if ($storeOnly) {
                $output->writeln(sprintf('Saved run id: %d.', $run->getId()));
                return Command::SUCCESS;
            }
        }

        try {
            $result = $this->importService->importParsedRows($run, $parseResult, $utc);
        } catch (\Throwable $e) {
            $output->writeln('<error>Import failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Saved %d rows, updated %d (run id: %d).', $result['saved'], $result['updated'], $run->getId()));

        if (!$skipSun) {
            $sunStep = $step ?: '1h';
            $updated = $this->updateSunGeocentric($start, $stop, $sunStep, $utc, $output);
            if ($updated === null) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function updateSunGeocentric(
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        string $step,
        \DateTimeZone $utc,
        OutputInterface $output
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
}
