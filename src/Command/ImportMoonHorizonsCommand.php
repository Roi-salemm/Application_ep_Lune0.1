<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsImportService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
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
            ->addOption('quantities', null, InputOption::VALUE_OPTIONAL, 'Horizons quantities list', '1,20,23,24,29')
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

        return Command::SUCCESS;
    }
}
