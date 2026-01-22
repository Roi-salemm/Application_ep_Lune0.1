<?php

namespace App\Command;

use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use App\Service\Moon\Phase\MoonPhaseEventCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:moon:compute-phase-events',
    description: 'Compute moon phase events from ephemeris data.',
)]
class ComputeMoonPhaseEventsCommand extends Command
{
    public function __construct(
        private MoonPhaseEventCalculatorService $calculatorService,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start time (UTC). Example: 2026-01-01', 'now')
            ->addOption('stop', null, InputOption::VALUE_OPTIONAL, 'Stop time (UTC). Example: 2026-12-31')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to process when stop is not provided', '365')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compute without saving to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $dryRun = (bool) $input->getOption('dry-run');

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

        $result = $this->calculatorService->calculateAndPersist($start, $stop, $utc, $dryRun);

        $output->writeln(sprintf(
            'Events total %d | saved %d | updated %d',
            $result['total'],
            $result['saved'],
            $result['updated']
        ));

        return Command::SUCCESS;
    }
}
