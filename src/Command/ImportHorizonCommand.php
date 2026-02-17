<?php

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

/**
 * Importe les reponses brutes Horizons (Moon + Sun) dans import_horizon.
 * Pourquoi: conserver un dump complet des donnees geocentriques utiles.
 * Infos: la commande ne parse pas, elle stocke uniquement les raw_response.
 */
#[AsCommand(
    name: 'app:horizon:import',
    description: 'Download raw Horizons data (Moon + Sun) and store in import_horizon.',
)]
class ImportHorizonCommand extends Command
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
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start time (UTC). Example: 2026-01-01 00:00', 'now')
            ->addOption('stop', null, InputOption::VALUE_OPTIONAL, 'Stop time (UTC). Example: 2026-01-08 00:00')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to import when stop is not provided', '7')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Horizons step size', '10m')
            ->addOption('moon-target', null, InputOption::VALUE_OPTIONAL, 'Moon target body', '301')
            ->addOption('sun-target', null, InputOption::VALUE_OPTIONAL, 'Sun target body', '10')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('quantities', null, InputOption::VALUE_OPTIONAL, 'Horizons quantities list (must match the expected GEO list)', self::MOON_QUANTITIES)
            ->addOption('skip-sun', null, InputOption::VALUE_NONE, 'Skip fetching Sun data')
            ->addOption('time-zone', null, InputOption::VALUE_OPTIONAL, 'Time zone label stored in the run', 'UTC')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch without saving to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');
        $skipSun = (bool) $input->getOption('skip-sun');

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

        $moonTarget = trim((string) $input->getOption('moon-target')) ?: '301';
        $sunTarget = trim((string) $input->getOption('sun-target')) ?: '10';
        $center = trim((string) $input->getOption('center')) ?: '500@399';
        $step = trim((string) $input->getOption('step')) ?: '10m';
        if ($center !== '500@399') {
            $output->writeln('<error>Center geocentrique requis: 500@399.</error>');
            return Command::FAILURE;
        }
        $quantitiesInput = trim((string) $input->getOption('quantities'));
        if ($quantitiesInput !== '') {
            $normalized = $this->clientService->normalizeQuantities($quantitiesInput);
            if ($normalized !== self::MOON_QUANTITIES) {
                $output->writeln('<error>Quantities non supportees pour cet import.</error>');
                return Command::FAILURE;
            }
        }
        $moonQuantities = self::MOON_QUANTITIES;
        $sunQuantities = self::SUN_QUANTITIES;
        $timeZoneLabel = trim((string) $input->getOption('time-zone')) ?: 'UTC';

        $output->writeln('Requesting Moon ephemeris from NASA JPL Horizons...');

        try {
            $moonResponse = $this->clientService->fetchEphemeris(
                $start,
                $stop,
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
            $this->importService->createRun(
                $moonTarget,
                $center,
                $start,
                $stop,
                $step,
                $timeZoneLabel,
                $moonResponse['body'],
                $moonHeader,
                [
                    'target' => $moonTarget,
                    'site' => $center,
                    'center' => $center,
                    'start' => $start->format('Y-m-d H:i'),
                    'stop' => $stop->format('Y-m-d H:i'),
                    'step' => $step,
                    'quantities' => $moonQuantities,
                    'ephem_type' => 'OBSERVER',
                    'out_units' => 'KM-S',
                    'ang_format' => self::ANG_FORMAT,
                    'time_zone' => $timeZoneLabel,
                ],
                $utc
            );
        }

        if ($skipSun) {
            return Command::SUCCESS;
        }

        $output->writeln('Requesting Sun ephemeris from NASA JPL Horizons...');

        try {
            $sunResponse = $this->clientService->fetchEphemeris(
                $start,
                $stop,
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
            return Command::SUCCESS;
        }

        $sunHeader = $this->parserService->parseResponse($sunResponse['body'])->getHeaderLine();
        $this->importService->createRun(
            $sunTarget,
            $center,
            $start,
            $stop,
            $step,
            $timeZoneLabel,
            $sunResponse['body'],
            $sunHeader,
            [
                'target' => $sunTarget,
                'site' => $center,
                'center' => $center,
                'start' => $start->format('Y-m-d H:i'),
                'stop' => $stop->format('Y-m-d H:i'),
                'step' => $step,
                'quantities' => $sunQuantities,
                'ephem_type' => 'OBSERVER',
                'out_units' => 'KM-S',
                'ang_format' => self::ANG_FORMAT,
                'time_zone' => $timeZoneLabel,
            ],
            $utc
        );

        return Command::SUCCESS;
    }
}
