<?php

namespace App\Command;

use App\Repository\MonthParseCoverageRepository;
use App\Service\Moon\MsMappingParseService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parse canonique_data pour construire ms_mapping, mois par mois.
 * Pourquoi: aggregation journaliere avec phase + heure de changement.
 */
#[AsCommand(
    name: 'app:moon:parse-ms-mapping',
    description: 'Build ms_mapping from canonique_data (month by month).',
)]
class ParseMsMappingCommand extends Command
{
    public function __construct(
        private MsMappingParseService $parseService,
        private MonthParseCoverageRepository $coverageRepository,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start month (UTC). Example: 2026-01-01', 'now')
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Number of months to parse', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $input->getOption('start');
        $months = max(1, (int) $input->getOption('months'));

        $start = $this->dateTimeParserService->parseInput($startInput, $utc);
        if (!$start) {
            $output->writeln('<error>Invalid --start value.</error>');
            return Command::FAILURE;
        }

        $start = (clone $start)->setTimezone($utc)->modify('first day of this month')->setTime(0, 0, 0);

        for ($index = 0; $index < $months; $index++) {
            $monthStart = (clone $start)->modify(sprintf('+%d months', $index));
            $monthStop = (clone $monthStart)->modify('+1 month');

            $output->writeln(sprintf(
                'Parse %s -> %s (UTC)',
                $monthStart->format('Y-m-d H:i'),
                $monthStop->format('Y-m-d H:i')
            ));

            $result = $this->parseService->parseMonth(
                \DateTimeImmutable::createFromInterface($monthStart),
                \DateTimeImmutable::createFromInterface($monthStop),
                $utc
            );

            $output->writeln(sprintf(
                'Saved %d, updated %d, events %d',
                $result['saved'],
                $result['updated'],
                $result['events']
            ));

            if ($result['missing_days']) {
                $output->writeln('Missing 12:00 UTC days: ' . implode(', ', $result['missing_days']));
            }

            $count = $result['saved'] + $result['updated'];
            if ($count > 0) {
                $this->coverageRepository->upsertMonthStatus(
                    MonthParseCoverageRepository::TARGET_MS_MAPPING,
                    $monthStart->format('Y-m'),
                    MonthParseCoverageRepository::STATUS_PARSED,
                    new \DateTimeImmutable('now', $utc)
                );
            }
        }

        return Command::SUCCESS;
    }
}
