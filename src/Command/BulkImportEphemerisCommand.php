<?php

/**
 * Importe en masse des reponses Horizons mois par mois (Moon + Sun).
 * Pourquoi: constituer un historique d'import brut geocentrique.
 * Infos: ne parse pas, il stocke uniquement dans import_horizon.
 */

namespace App\Command;

use App\Service\Horizon\ImportHorizonService;
use App\Service\Moon\Horizons\MoonHorizonsClientService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Horizons\MoonHorizonsParserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:horizon:bulk-import',
    description: 'Download raw Horizons data month by month (store-only).',
)]
class BulkImportEphemerisCommand extends Command
{
    private const MOON_QUANTITIES = '1,2,10,20,31,43,29';
    private const SUN_QUANTITIES = '31';
    private const ANG_FORMAT = 'DEG';

    public function __construct(
        private MoonHorizonsClientService $clientService,
        private MoonHorizonsParserService $parserService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
        private ImportHorizonService $importService,
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
            ->addOption('moon-quantities', null, InputOption::VALUE_OPTIONAL, 'Moon quantities list (must match the expected GEO list)', self::MOON_QUANTITIES)
            ->addOption('sun-target', null, InputOption::VALUE_OPTIONAL, 'Sun target body', '10')
            ->addOption('sun-quantities', null, InputOption::VALUE_OPTIONAL, 'Sun quantities list (must match the expected GEO list)', self::SUN_QUANTITIES)
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
        if ($center !== '500@399') {
            $output->writeln('<error>Center geocentrique requis: 500@399.</error>');
            return Command::FAILURE;
        }
        $moonQuantitiesInput = trim((string) $input->getOption('moon-quantities'));
        if ($moonQuantitiesInput !== '') {
            $normalized = $this->clientService->normalizeQuantities($moonQuantitiesInput);
            if ($normalized !== self::MOON_QUANTITIES) {
                $output->writeln('<error>Moon quantities non supportees pour cet import.</error>');
                return Command::FAILURE;
            }
        }
        $moonQuantities = self::MOON_QUANTITIES;
        $sunTarget = trim((string) $input->getOption('sun-target')) ?: '10';
        $sunQuantitiesInput = trim((string) $input->getOption('sun-quantities'));
        if ($sunQuantitiesInput !== '') {
            $normalized = $this->clientService->normalizeQuantities($sunQuantitiesInput);
            if ($normalized !== self::SUN_QUANTITIES) {
                $output->writeln('<error>Sun quantities non supportees pour cet import.</error>');
                return Command::FAILURE;
            }
        }
        $sunQuantities = self::SUN_QUANTITIES;
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
                    $moonQuantities,
                    ['ANG_FORMAT' => self::ANG_FORMAT]
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
                $moonHeader = $this->parserService->parseResponse($moonResponse['body'])->getHeaderLine();
                $moonRun = $this->importService->createRun(
                    $moonTarget,
                    $center,
                    $chunkStart,
                    $chunkStop,
                    $step,
                    $timeZoneLabel,
                    $moonResponse['body'],
                    $moonHeader,
                    [
                        'target' => $moonTarget,
                        'site' => $center,
                        'center' => $center,
                        'start' => $chunkStart->format('Y-m-d H:i'),
                        'stop' => $chunkStop->format('Y-m-d H:i'),
                        'step' => $step,
                        'quantities' => $moonQuantities,
                        'ephem_type' => 'OBSERVER',
                        'out_units' => 'KM-S',
                        'ang_format' => self::ANG_FORMAT,
                        'time_zone' => $timeZoneLabel,
                    ],
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
                    ['ANG_FORMAT' => self::ANG_FORMAT]
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
                $sunHeader = $this->parserService->parseResponse($sunResponse['body'])->getHeaderLine();
                $sunRun = $this->importService->createRun(
                    $sunTarget,
                    $center,
                    $chunkStart,
                    $chunkStop,
                    $step,
                    $timeZoneLabel,
                    $sunResponse['body'],
                    $sunHeader,
                    [
                        'target' => $sunTarget,
                        'site' => $center,
                        'center' => $center,
                        'start' => $chunkStart->format('Y-m-d H:i'),
                        'stop' => $chunkStop->format('Y-m-d H:i'),
                        'step' => $step,
                        'quantities' => $sunQuantities,
                        'ephem_type' => 'OBSERVER',
                        'out_units' => 'KM-S',
                        'ang_format' => self::ANG_FORMAT,
                        'time_zone' => $timeZoneLabel,
                    ],
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
