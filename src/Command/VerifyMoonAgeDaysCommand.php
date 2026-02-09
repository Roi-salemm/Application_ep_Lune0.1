<?php

/**
 * Verifie la coherence de age_days entre raw_response et moon_ephemeris_hour.
 * Pourquoi: detecter des decallages de parsing ou de mapping Horizons.
 * Infos: compare les valeurs par timestamp et remonte un echantillon d ecarts.
 */

namespace App\Command;

use App\Entity\ImportHorizon;
use App\Repository\MoonEphemerisHourRepository;
use App\Repository\ImportHorizonRepository;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use App\Service\Moon\Horizons\MoonHorizonsRowMapperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:moon:verify-age-days',
    description: 'Compare age_days between raw_line (Horizons) and moon_ephemeris_hour.',
)]
class VerifyMoonAgeDaysCommand extends Command
{
    private const DEFAULT_MISMATCH_LIMIT = 20;
    private const DEFAULT_TOLERANCE = 0.0005;

    public function __construct(
        private ImportHorizonRepository $runRepository,
        private MoonEphemerisHourRepository $hourRepository,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsRowMapperService $rowMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('run-id', null, InputOption::VALUE_OPTIONAL, 'Run id to check (default: latest)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max mismatches to print', (string) self::DEFAULT_MISMATCH_LIMIT)
            ->addOption('tolerance', null, InputOption::VALUE_OPTIONAL, 'Tolerance for age_days comparison', (string) self::DEFAULT_TOLERANCE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $runIdOption = $input->getOption('run-id');
        $limit = max(1, (int) $input->getOption('limit'));
        $tolerance = (float) $input->getOption('tolerance');

        $run = $runIdOption !== null
            ? $this->runRepository->find((int) $runIdOption)
            : $this->findLatestRun();

        if (!$run) {
            $output->writeln('<error>Run introuvable.</error>');
            return Command::FAILURE;
        }

        $rawResponse = $run->getRawResponse();
        if (!$rawResponse) {
            $output->writeln('<error>Raw response vide pour ce run.</error>');
            return Command::FAILURE;
        }

        $parseResult = $this->parserService->parseResponse($rawResponse);
        $columnMap = $parseResult->getColumnMap();
        $ageIndex = $columnMap['age_days'] ?? null;

        if ($ageIndex === null) {
            $output->writeln('<error>Colonne AGE/LUNAR AGE introuvable dans le header Horizons.</error>');
            $headerLine = $parseResult->getHeaderLine();
            $header = $parseResult->getHeader();
            if ($headerLine) {
                $output->writeln('Header line: ' . $headerLine);
            } else {
                $output->writeln('Header line: <not found>');
            }
            if ($header) {
                $output->writeln('Columns: ' . implode(' | ', $header));
            } else {
                $output->writeln('Columns: <no header parsed>');
            }
            return Command::FAILURE;
        }

        $hours = $this->hourRepository->findBy(['run_id' => $run->getId()], ['ts_utc' => 'ASC']);
        if (!$hours) {
            $output->writeln('<comment>Aucune ligne moon_ephemeris_hour pour ce run.</comment>');
            return Command::SUCCESS;
        }

        $rawRowsByTimestamp = [];
        foreach ($parseResult->getRows() as $row) {
            $timestamp = $this->rowMapper->parseTimestamp($row, $columnMap, $utc);
            if (!$timestamp) {
                continue;
            }
            $key = $timestamp->format('Y-m-d H:i');
            if (!isset($rawRowsByTimestamp[$key])) {
                $rawRowsByTimestamp[$key] = $row;
            }
        }

        $total = count($hours);
        $checked = 0;
        $ok = 0;
        $mismatch = 0;
        $missingRawLine = 0;
        $missingRawRow = 0;
        $missingAgeColumn = 0;
        $missingRawAge = 0;
        $missingDbAge = 0;

        $samples = [];

        foreach ($hours as $hour) {
            $ts = $hour->getTsUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }

            $timestampKey = $ts->format('Y-m-d H:i');
            $rawRow = $rawRowsByTimestamp[$timestampKey] ?? null;
            if (!$rawRow) {
                $missingRawRow++;
                continue;
            }

            $rawLineImport = $rawRow['raw'] ?? null;
            $cols = $rawRow['cols'] ?? [];
            if ($rawLineImport === null || trim($rawLineImport) === '') {
                $missingRawRow++;
                continue;
            }

            $rawLine = $hour->getRawLine();
            if ($rawLine === null || trim($rawLine) === '') {
                $missingRawLine++;
                continue;
            }

            if (!array_key_exists($ageIndex, $cols)) {
                $missingAgeColumn++;
                continue;
            }

            $ageRaw = $this->parseDecimal($cols[$ageIndex] ?? null);
            if ($ageRaw === null) {
                $missingRawAge++;
                continue;
            }

            $ageDb = $this->parseDecimal($hour->getAgeDays());
            if ($ageDb === null) {
                $missingDbAge++;
                continue;
            }

            $checked++;
            $diff = abs($ageRaw - $ageDb);

            if ($diff > $tolerance) {
                $mismatch++;
                if (count($samples) < $limit) {
                    $meta = sprintf('ts=%s diff=%.6f', $ts->format('Y-m-d H:i'), $diff);
                    $labelWidth = 11;
                    $line1 = sprintf('%s | %-' . $labelWidth . 's %s', $meta, 'raw_import:', $rawLineImport);
                    $line2 = sprintf('%s | %-' . $labelWidth . 's %s', $meta, 'raw_db:', $rawLine);
                    $samples[] = $line1 . "\n" . $line2;
                }
            } else {
                $ok++;
            }
        }

        $output->writeln(sprintf(
            'Run id: %d | status: %s | step: %s',
            $run->getId(),
            $run->getStatus() ?? 'n/a',
            $run->getStepSize() ?? 'n/a'
        ));
        $output->writeln('Age column index: ' . $ageIndex);
        $output->writeln(sprintf(
            'Rows: %d | Checked: %d | OK: %d | Mismatch: %d',
            $total,
            $checked,
            $ok,
            $mismatch
        ));
        $output->writeln(sprintf(
            'Missing raw_row: %d | Missing raw_line: %d | Missing age col: %d | Missing raw age: %d | Missing db age: %d',
            $missingRawRow,
            $missingRawLine,
            $missingAgeColumn,
            $missingRawAge,
            $missingDbAge
        ));

        if ($samples) {
            $output->writeln('--- Mismatches (sample) ---');
            foreach ($samples as $line) {
                $output->writeln($line);
            }
        }

        return $mismatch > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function findLatestRun(): ?ImportHorizon
    {
        return $this->runRepository->createQueryBuilder('m')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function parseDecimal(?string $value): ?float
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

        $direction = null;
        if (preg_match('/^([+-]?\d+(?:\.\d+)?(?:[Ee][+-]?\d+)?)([NSEW])$/i', $clean, $matches) === 1) {
            $clean = $matches[1];
            $direction = strtoupper($matches[2]);
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?([Ee][+-]?\d+)?$/', $clean)) {
            return null;
        }

        if ($direction) {
            $unsigned = ltrim($clean, '+-');
            if (in_array($direction, ['S', 'W'], true)) {
                $clean = '-' . $unsigned;
            } else {
                $clean = $unsigned;
            }
        }

        return (float) $clean;
    }

    private function formatFloat(float $value): string
    {
        $formatted = sprintf('%.6f', $value);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
