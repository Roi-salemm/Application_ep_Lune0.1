<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:ephemeris:bulk-import',
    description: 'Download raw Moon ephemeris data month by month (store-only).',
)]
class BulkImportEphemerisCommand extends Command
{
    public function __construct(
        private MoonHorizonsClientService $clientService,
        private MoonHorizonsImportService $moonImportService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start month (UTC). Example: 2026-01-01', 'now')
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Number of months to import', '1')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Horizons step size', '10m')
            ->addOption('moon-target', null, InputOption::VALUE_OPTIONAL, 'Moon target body', '301')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('moon-quantities', null, InputOption::VALUE_OPTIONAL, 'Moon quantities list', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49')
            ->addOption('sun-target', null, InputOption::VALUE_OPTIONAL, 'Sun target body', '10')
            ->addOption('sun-quantities', null, InputOption::VALUE_OPTIONAL, 'Sun quantities list', '1,20,31')
            ->addOption('sun-range-units', null, InputOption::VALUE_OPTIONAL, 'Sun range units', 'AU')
            ->addOption('time-zone', null, InputOption::VALUE_OPTIONAL, 'Time zone label stored in the run', 'UTC')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and parse without saving to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');
        $startInput = (string) $input->getOption('start');
        $months = max(1, (int) $input->getOption('months'));
        $step = trim((string) $input->getOption('step')) ?: '10m';
        $moonTarget = trim((string) $input->getOption('moon-target')) ?: '301';
        $center = trim((string) $input->getOption('center')) ?: '500@399';
        $moonQuantities = trim((string) $input->getOption('moon-quantities')) ?: '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49';
        $sunTarget = trim((string) $input->getOption('sun-target')) ?: '10';
        $sunQuantities = trim((string) $input->getOption('sun-quantities')) ?: '1,20,31';
        $sunRangeUnits = trim((string) $input->getOption('sun-range-units')) ?: 'AU';
        $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

        $start = $this->dateTimeParserService->parseInput($startInput, $utc);
        if (!$start) {
            $output->writeln('<error>Invalid --start value.</error>');
            return Command::FAILURE;
        }

        $start = (clone $start)->setTimezone($utc)->modify('first day of this month')->setTime(0, 0, 0);
        $stepSeconds = $this->parseStepToSeconds($step);
        if ($stepSeconds <= 0) {
            $stepSeconds = 600;
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

            if ($dryRun) {
                $output->writeln(sprintf('Moon raw bytes: %d', strlen($moonResponse['body'])));
            } else {
                $moonRun = $this->moonImportService->createRun(
                    $moonTarget,
                    $center,
                    $chunkStart,
                    $chunkStop,
                    $step,
                    $timeZoneLabel,
                    $moonResponse['body'],
                    true,
                    $utc
                );
                $output->writeln(sprintf('Moon run saved (id: %d).', $moonRun->getId()));
            }

            try {
                $sunResponse = $this->clientService->fetchEphemeris(
                    $chunkStart,
                    $chunkStop,
                    $sunTarget,
                    $center,
                    $step,
                    $sunQuantities,
                    ['RANGE_UNITS' => $sunRangeUnits]
                );
            } catch (\RuntimeException $e) {
                $output->writeln('<error>Sun fetch failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            if ($sunResponse['status'] >= 400) {
                $output->writeln('<error>Sun Horizons HTTP ' . $sunResponse['status'] . '.</error>');
                return Command::FAILURE;
            }

            if ($dryRun) {
                $output->writeln(sprintf('Sun raw bytes: %d', strlen($sunResponse['body'])));
            } else {
                $sunRun = $this->moonImportService->createRun(
                    $sunTarget,
                    $center,
                    $chunkStart,
                    $chunkStop,
                    $step,
                    $timeZoneLabel,
                    $sunResponse['body'],
                    true,
                    $utc
                );
                $output->writeln(sprintf('Sun run saved (id: %d).', $sunRun->getId()));
            }
        }

        return Command::SUCCESS;
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
