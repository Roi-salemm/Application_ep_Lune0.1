<?php

namespace App\Controller\Admin;

use App\Repository\ImportHorizonRepository;
use App\Repository\MsMappingRepository;
use App\Repository\MonthParseCoverageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controle l'onglet admin ms_mapping.
 * Pourquoi: afficher l'agregation journaliere et declencher le parse mensuel.
 * Infos: s'appuie sur month_parse_coverage et les imports Horizons pour les statuts.
 */
final class MsMappingController extends AbstractController
{
    private const START_YEAR = 1920;
    private const END_YEAR = 2150;
    private const CENTER = '500@399';
    private const STEP = '10m';
    private const MOON_TARGET = '301';
    private const MOON_TARGET_FALLBACK = '300';
    private const SUN_TARGET = '10';

    #[Route('/admin/ms_mapping', name: 'admin_ms_mapping', methods: ['GET'])]
    public function index(
        Request $request,
        MsMappingRepository $repository,
        ImportHorizonRepository $importRepository,
        MonthParseCoverageRepository $coverageRepository
    ): Response {
        $limit = 200;
        $page = max(1, (int) $request->query->get('page', 1));
        $totalCount = $repository->countAll();
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $limit) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;
        $rows = $repository->findLatestPaged($limit, $offset);
        $columns = $this->orderColumns($repository->fetchColumnNames());
        $nextMonthStart = $this->resolveNextMonthStart($repository, new \DateTimeZone('UTC'));
        $utc = new \DateTimeZone('UTC');
        $monthCoverage = $this->buildMsMappingMonthCoverage($coverageRepository, $importRepository, $utc);
        $yearCoverage = $this->buildYearCoverage($monthCoverage);

        return $this->render('admin/ms_mapping.html.twig', [
            'rows' => $rows,
            'columns' => $columns,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'next_month_start' => $nextMonthStart->format('Y-m-01'),
            'next_month_label' => $this->formatMonthLabel($nextMonthStart),
            'calendar_years' => range(self::START_YEAR, self::END_YEAR),
            'month_coverage' => $monthCoverage,
            'year_coverage' => $yearCoverage,
        ]);
    }

    #[Route('/admin/ms_mapping/parse-month', name: 'admin_ms_mapping_parse_month', methods: ['POST'])]
    public function parseMonth(Request $request, KernelInterface $kernel): Response
    {
        $start = trim((string) $request->request->get('start', ''));
        if ($start === '') {
            $this->addFlash('error', 'Mois invalide.');
            return $this->redirectToRoute('admin_ms_mapping');
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:parse-ms-mapping',
            '--start=' . $start,
            '--months=1',
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Parse echoue.');
        } else {
            $output = trim($process->getOutput());
            $missing = $this->extractMissingDays($output);
            if ($missing) {
                $output = trim(preg_replace('/^.*Missing 12:00 UTC days:.*$/mi', '', $output) ?? $output);
            }
            $this->addFlash('success', $output !== '' ? $output : 'Parse termine.');

            if ($missing) {
                $this->addFlash(
                    'warning',
                    'Jours manquants (12:00 UTC): ' . implode(', ', $missing)
                );
            }
        }

        return $this->redirectToRoute('admin_ms_mapping');
    }

    #[Route('/admin/ms_mapping/parse-year', name: 'admin_ms_mapping_parse_year', methods: ['POST'])]
    public function parseYear(Request $request, KernelInterface $kernel): Response
    {
        $yearInput = (int) $request->request->get('year', 0);
        if ($yearInput < self::START_YEAR || $yearInput > self::END_YEAR) {
            $this->addFlash('error', 'Annee invalide.');
            return $this->redirectToRoute('admin_ms_mapping');
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:parse-ms-mapping',
            '--start=' . sprintf('%04d-01-01', $yearInput),
            '--months=12',
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Parse annuel echoue.');
        } else {
            $output = trim($process->getOutput());
            $missing = $this->extractMissingDays($output);
            if ($missing) {
                $output = trim(preg_replace('/^.*Missing 12:00 UTC days:.*$/mi', '', $output) ?? $output);
            }
            $this->addFlash('success', $output !== '' ? $output : 'Parse annuel termine.');

            if ($missing) {
                $this->addFlash(
                    'warning',
                    'Jours manquants (12:00 UTC): ' . implode(', ', $missing)
                );
            }
        }

        return $this->redirectToRoute('admin_ms_mapping');
    }

    #[Route('/admin/ms_mapping/delete-year', name: 'admin_ms_mapping_delete_year', methods: ['POST'])]
    public function deleteYear(
        Request $request,
        MsMappingRepository $repository,
        MonthParseCoverageRepository $coverageRepository
    ): Response
    {
        $yearInput = (int) $request->request->get('year', 0);
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_ms_mapping_year_' . $yearInput, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_ms_mapping');
        }

        if ($yearInput < self::START_YEAR || $yearInput > self::END_YEAR) {
            $this->addFlash('error', 'Annee invalide.');
            return $this->redirectToRoute('admin_ms_mapping');
        }

        $utc = new \DateTimeZone('UTC');
        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $yearInput), $utc);
        $stop = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $yearInput + 1), $utc);

        $deleted = $repository->deleteByTimestampRange($start, $stop);
        $coverageRepository->deleteYear(MonthParseCoverageRepository::TARGET_MS_MAPPING, $yearInput);
        $this->addFlash('success', sprintf('Suppression terminee: %d lignes supprimees.', $deleted));

        return $this->redirectToRoute('admin_ms_mapping');
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    private function orderColumns(array $columns): array
    {
        if (!$columns) {
            return [];
        }

        $fixed = [
            'id',
            'ts_utc',
            'm43_pab_lon_deg',
            'm10_illum_frac',
            'm31_ecl_lon_deg',
            's31_ecl_lon_deg',
            'phase',
            'phase_hour',
        ];
        $ordered = [];
        foreach ($fixed as $name) {
            if (in_array($name, $columns, true)) {
                $ordered[] = $name;
            }
        }

        $rest = array_values(array_diff($columns, $ordered));
        sort($rest);

        return array_merge($ordered, $rest);
    }

    private function resolveNextMonthStart(
        MsMappingRepository $repository,
        \DateTimeZone $utc
    ): \DateTimeImmutable {
        $max = $repository->findMaxTimestamp();

        if (!$max) {
            return (new \DateTimeImmutable('now', $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        }

        return $max
            ->setTimezone($utc)
            ->modify('first day of this month')
            ->setTime(0, 0, 0)
            ->modify('+1 month');
    }

    private function formatMonthLabel(\DateTimeInterface $date): string
    {
        $monthNames = [
            1 => 'Janvier',
            2 => 'Fevrier',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Aout',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Decembre',
        ];

        $monthNumber = (int) $date->format('n');
        $monthLabel = $monthNames[$monthNumber] ?? $date->format('F');

        return sprintf('%s %s (%s)', $date->format('Y'), $date->format('m'), $monthLabel);
    }

    /**
     * @return array<string, string>
     */
    private function buildMsMappingMonthCoverage(
        MonthParseCoverageRepository $coverageRepository,
        ImportHorizonRepository $importRepository,
        \DateTimeZone $utc
    ): array {
        $mappingMonths = $coverageRepository->findMonthCoverage(MonthParseCoverageRepository::TARGET_MS_MAPPING);
        $mappingSet = array_fill_keys($mappingMonths, true);

        $canoniqueMonths = $coverageRepository->findMonthCoverage(MonthParseCoverageRepository::TARGET_CANONIQUE_DATA);
        $canoniqueSet = array_fill_keys($canoniqueMonths, true);

        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', self::START_YEAR), $utc);
        $end = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', self::END_YEAR + 1), $utc);

        $moonRuns = array_merge(
            $this->normalizeRuns(
                $importRepository->findRunsOverlappingPeriod(self::MOON_TARGET, $start, $end, self::CENTER, self::STEP),
                $utc
            ),
            $this->normalizeRuns(
                $importRepository->findRunsOverlappingPeriod(self::MOON_TARGET_FALLBACK, $start, $end, self::CENTER, self::STEP),
                $utc
            )
        );
        $sunRuns = $this->normalizeRuns(
            $importRepository->findRunsOverlappingPeriod(self::SUN_TARGET, $start, $end, self::CENTER, self::STEP),
            $utc
        );

        $coverage = [];
        $stepSeconds = $this->parseStepToSeconds(self::STEP);

        for ($year = self::START_YEAR; $year <= self::END_YEAR; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $utc);
                $monthEnd = $monthStart->modify('+1 month');
                $expectedStop = $stepSeconds > 0
                    ? $monthEnd->modify(sprintf('-%d seconds', $stepSeconds))
                    : $monthEnd;
                $monthKey = $monthStart->format('Y-m');

                if (isset($mappingSet[$monthKey])) {
                    $coverage[$monthKey] = 'parsed';
                    continue;
                }

                if (isset($canoniqueSet[$monthKey])) {
                    $coverage[$monthKey] = 'canonique';
                    continue;
                }

                $moonStatus = $this->resolveCoverageStatus(
                    $moonRuns,
                    $monthStart->getTimestamp(),
                    $expectedStop->getTimestamp()
                );
                $sunStatus = $this->resolveCoverageStatus(
                    $sunRuns,
                    $monthStart->getTimestamp(),
                    $expectedStop->getTimestamp()
                );

                if ($moonStatus['any'] || $sunStatus['any']) {
                    $coverage[$monthKey] = 'ready';
                } else {
                    $coverage[$monthKey] = 'missing';
                }
            }
        }

        return $coverage;
    }

    /**
     * @param array<string, string> $monthCoverage
     * @return array<int, string>
     */
    private function buildYearCoverage(array $monthCoverage): array
    {
        $yearCoverage = [];
        for ($year = self::START_YEAR; $year <= self::END_YEAR; $year++) {
            $allParsed = true;
            $hasCanonique = false;
            $hasReady = false;
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%04d-%02d', $year, $month);
                $status = $monthCoverage[$key] ?? 'missing';
                if ($status !== 'parsed') {
                    $allParsed = false;
                }
                if (in_array($status, ['parsed', 'canonique'], true)) {
                    $hasCanonique = true;
                }
                if ($status === 'ready') {
                    $hasReady = true;
                }
            }

            if ($allParsed) {
                $yearCoverage[$year] = 'parsed';
            } elseif ($hasCanonique) {
                $yearCoverage[$year] = 'canonique';
            } elseif ($hasReady) {
                $yearCoverage[$year] = 'ready';
            } else {
                $yearCoverage[$year] = 'missing';
            }
        }

        return $yearCoverage;
    }

    /**
     * @return string[]
     */
    private function extractMissingDays(string $output): array
    {
        if (preg_match('/Missing 12:00 UTC days:\s*(.+)$/mi', $output, $matches) !== 1) {
            return [];
        }

        $list = array_map('trim', explode(',', $matches[1]));
        return array_values(array_filter($list));
    }

    /**
     * @param array<int, array{start_utc: \DateTimeInterface|null, stop_utc: \DateTimeInterface|null}> $runs
     * @return array<int, array{0: int, 1: int}>
     */
    private function normalizeRuns(array $runs, \DateTimeZone $utc): array
    {
        $ranges = [];
        foreach ($runs as $run) {
            $start = $run['start_utc'] ?? null;
            $stop = $run['stop_utc'] ?? null;
            if (!$start || !$stop) {
                continue;
            }
            $startTs = \DateTimeImmutable::createFromInterface($start)->setTimezone($utc)->getTimestamp();
            $stopTs = \DateTimeImmutable::createFromInterface($stop)->setTimezone($utc)->getTimestamp();
            $ranges[] = [$startTs, $stopTs];
        }

        return $ranges;
    }

    /**
     * @param array<int, array{0: int, 1: int}> $ranges
     * @return array{any: bool, full: bool}
     */
    private function resolveCoverageStatus(array $ranges, int $monthStart, int $expectedStop): array
    {
        $hasAny = false;
        $hasFull = false;
        foreach ($ranges as [$start, $stop]) {
            if ($stop < $monthStart || $start > $expectedStop) {
                continue;
            }
            $hasAny = true;
            if ($start <= $monthStart && $stop >= $expectedStop) {
                $hasFull = true;
                break;
            }
        }

        return ['any' => $hasAny, 'full' => $hasFull];
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
