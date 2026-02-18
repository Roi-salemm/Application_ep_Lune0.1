<?php

namespace App\Command;

use App\Repository\CanoniqueDataRepository;
use App\Repository\ImportHorizonRepository;
use App\Repository\MonthParseCoverageRepository;
use App\Service\Horizon\CanoniqueDataParseService;
use App\Service\Moon\Horizons\MoonHorizonsDateTimeParserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parse les imports Horizons pour remplir canonique_data, mois par mois.
 * Pourquoi: constituer une base brute stable sans calculs applicatifs.
 * Infos: requiert des runs Moon + Sun couvrant la periode.
 */
#[AsCommand(
    name: 'app:horizon:parse-canonique',
    description: 'Parse import_horizon runs into canonique_data (month by month).',
)]
class ParseCanoniqueDataCommand extends Command
{
    public function __construct(
        private ImportHorizonRepository $importRepository,
        private CanoniqueDataRepository $canoniqueRepository,
        private CanoniqueDataParseService $parseService,
        private MonthParseCoverageRepository $coverageRepository,
        private MoonHorizonsDateTimeParserService $dateTimeParserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start month (UTC). Example: 2026-01-01', 'now')
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Number of months to parse', '1')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Expected step size', '10m')
            ->addOption('center', null, InputOption::VALUE_OPTIONAL, 'Center body or site', '500@399')
            ->addOption('moon-target', null, InputOption::VALUE_OPTIONAL, 'Moon target body', '301')
            ->addOption('sun-target', null, InputOption::VALUE_OPTIONAL, 'Sun target body', '10')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Delete month before parsing to fully replace data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $input->getOption('start');
        $months = max(1, (int) $input->getOption('months'));
        $step = trim((string) $input->getOption('step')) ?: '10m';
        $center = trim((string) $input->getOption('center')) ?: '500@399';
        $moonTarget = trim((string) $input->getOption('moon-target')) ?: '301';
        $sunTarget = trim((string) $input->getOption('sun-target')) ?: '10';
        $replace = (bool) $input->getOption('replace');

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
            $monthKey = $chunkStart->format('Y-m');

            $output->writeln(sprintf(
                'Parse %s -> %s (UTC)',
                $chunkStart->format('Y-m-d H:i'),
                $chunkStop->format('Y-m-d H:i')
            ));

            $moonRun = $this->importRepository->findLatestByTargetAndPeriod($moonTarget, $chunkStart, $chunkStop, $center, $step);
            if (!$moonRun) {
                $output->writeln('<error>Run Moon introuvable pour cette periode.</error>');
                continue;
            }

            $sunRun = $this->importRepository->findLatestByTargetAndPeriod($sunTarget, $chunkStart, $chunkStop, $center, $step);
            if (!$sunRun) {
                $output->writeln('<error>Run Sun introuvable pour cette periode.</error>');
                continue;
            }

            if ($replace) {
                $deleted = $this->canoniqueRepository->deleteByTimestampRange($chunkStart, $nextMonthStart);
                $output->writeln(sprintf('Replace mode: %d lignes supprimees.', $deleted));
                $this->coverageRepository->deleteMonth(MonthParseCoverageRepository::TARGET_CANONIQUE_DATA, $monthKey);
            }

            try {
                $moonResult = $this->parseService->parseRun($moonRun, 'm', $utc);
                $sunResult = $this->parseService->parseRun($sunRun, 's', $utc);
            } catch (\Throwable $e) {
                $output->writeln('<error>Parse echoue: ' . $e->getMessage() . '</error>');
                continue;
            }

            $output->writeln(sprintf(
                'Moon saved %d updated %d | Sun saved %d updated %d',
                $moonResult['saved'],
                $moonResult['updated'],
                $sunResult['saved'],
                $sunResult['updated']
            ));

            $moonCount = $moonResult['saved'] + $moonResult['updated'];
            $sunCount = $sunResult['saved'] + $sunResult['updated'];
            if ($moonCount > 0 && $sunCount > 0) {
                $this->coverageRepository->upsertMonthStatus(
                    MonthParseCoverageRepository::TARGET_CANONIQUE_DATA,
                    $monthKey,
                    MonthParseCoverageRepository::STATUS_PARSED,
                    new \DateTimeImmutable('now', $utc)
                );
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
