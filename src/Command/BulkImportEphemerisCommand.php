<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsImportService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use App\Service\Solar\Horizons\SolarHorizonsImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:ephemeris:bulk-import',
    description: 'Import Moon + Sun ephemeris data month by month.',
)]
class BulkImportEphemerisCommand extends Command
{
    public function __construct(
        private MoonHorizonsClientService $clientService,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsImportService $moonImportService,
        private SolarHorizonsImportService $solarImportService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
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
            ->addOption('sun-target', null, InputOption::VALUE_OPTIONAL, 'Sun target body', '10')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('moon-quantities', null, InputOption::VALUE_OPTIONAL, 'Moon quantities list', '1,20,23,24,29')
            ->addOption('sun-quantities', null, InputOption::VALUE_OPTIONAL, 'Sun quantities list', '1,20,23,24,29')
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
        $sunTarget = trim((string) $input->getOption('sun-target')) ?: '10';
        $center = trim((string) $input->getOption('center')) ?: '500@399';
        $moonQuantities = trim((string) $input->getOption('moon-quantities')) ?: '1,20,23,24,29';
        $sunQuantities = trim((string) $input->getOption('sun-quantities')) ?: '1,20,23,24,29';
        $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

        $start = $this->dateTimeParserService->parseInput($startInput, $utc);
        if (!$start) {
            $output->writeln('<error>Invalid --start value.</error>');
            return Command::FAILURE;
        }

        $start = (clone $start)->setTimezone($utc)->modify('first day of this month')->setTime(0, 0, 0);

        for ($index = 0; $index < $months; $index++) {
            $chunkStart = (clone $start)->modify(sprintf('+%d months', $index));
            $chunkStop = (clone $chunkStart)->modify('+1 month')->modify('-1 hour');

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

            try {
                $sunResponse = $this->clientService->fetchEphemeris(
                    $chunkStart,
                    $chunkStop,
                    $sunTarget,
                    $center,
                    $step,
                    $sunQuantities
                );
            } catch (\RuntimeException $e) {
                $output->writeln('<error>Sun fetch failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            if ($sunResponse['status'] >= 400) {
                $output->writeln('<error>Sun Horizons HTTP ' . $sunResponse['status'] . '.</error>');
                return Command::FAILURE;
            }

            $sunParse = $this->parserService->parseResponse($sunResponse['body']);

            if ($dryRun) {
                $output->writeln(sprintf('Sun rows: %d', count($sunParse->getRows())));
                continue;
            }

            $sunResult = $this->solarImportService->importParsedRows($sunParse, $utc, $chunkStart, $chunkStop);
            $output->writeln(sprintf('Sun saved %d, updated %d.', $sunResult['saved'], $sunResult['updated']));
        }

        return Command::SUCCESS;
    }
}
