<?php

namespace App\Controller\Admin;

use App\Repository\ImportHorizonRepository;
use App\Repository\MonthParseCoverageRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controle l'onglet admin des imports Horizons (table import_horizon).
 * Pourquoi: centraliser l'import mensuel et la liste des runs bruts.
 * Infos: utilise les imports geocentriques fixes (1,2,10,20,31,43 + Sun 31).
 */
final class MoonNasaImportController extends AbstractController
{
    private const MOON_TARGET = '301';
    private const MOON_TARGET_FALLBACK = '300';
    private const SUN_TARGET = '10';
    private const CENTER = '500@399';
    private const STEP = '10m';
    private const START_YEAR = 1920;
    private const END_YEAR = 2150;
    private const DELETE_TARGETS = [
        self::MOON_TARGET,
        self::MOON_TARGET_FALLBACK,
        self::SUN_TARGET,
    ];

    #[Route('/admin/horizon_data', name: 'admin_horizon_data', methods: ['GET'])]
    #[Route('/moon/imports', name: 'moon_imports_index', methods: ['GET'])]
    public function index(Request $request, ImportHorizonRepository $repository): Response
    {
        $limit = 200;
        $page = max(1, (int) $request->query->get('page', 1));
        $sort = strtolower((string) $request->query->get('sort', 'period'));
        $dir = strtolower((string) $request->query->get('dir', 'asc'));
        if (!in_array($sort, ['period', 'id'], true)) {
            $sort = 'period';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $totalCount = $repository->countAll();
        $totalPages = max(1, (int) ceil($totalCount / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $imports = $repository->findPage($page, $limit, $sort, $dir);
        $utc = new \DateTimeZone('UTC');
        $nextMonthStart = $this->resolveNextMonthStartFromImports($repository, $utc);
        $monthCoverage = $this->buildMonthCoverage($repository, $utc);
        $yearCoverage = $this->buildYearCoverage($monthCoverage);
        $years = range(self::START_YEAR, self::END_YEAR);

        return $this->render('admin/horizon_data.html.twig', [
            'imports' => $imports,
            'month_coverage' => $monthCoverage,
            'year_coverage' => $yearCoverage,
            'calendar_years' => $years,
            'next_month_label' => $this->formatMonthLabel($nextMonthStart),
            'next_month_start' => $nextMonthStart->format('Y-m-01'),
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/admin/horizon_data/run', name: 'admin_horizon_data_run', methods: ['POST'])]
    #[Route('/moon/imports/run', name: 'moon_imports_run', methods: ['POST'])]
    public function run(Request $request, KernelInterface $kernel): Response
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $request->request->get('start', '');
        $start = $this->parseDateTimeInput($startInput, $utc);
        if (!$start) {
            $this->addFlash('error', 'Start datetime invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $days = max(1, (int) $request->request->get('days', 7));
        $step = trim((string) $request->request->get('step', '10m'));
        if ($step === '') {
            $step = '10m';
        }

        $start->setTimezone($utc);
        $startString = $start->format('Y-m-d H:i');

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:horizon:import',
            '--start=' . $startString,
            '--days=' . $days,
            '--step=' . $step,
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Import echoue.');
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', $message !== '' ? $message : 'Import termine.');
        }

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/parse', name: 'admin_horizon_data_parse', methods: ['POST'])]
    #[Route('/moon/imports/parse', name: 'moon_imports_parse', methods: ['POST'])]
    public function parse(Request $request, KernelInterface $kernel): Response
    {
        $startInput = (string) $request->request->get('start', '');
        if ($startInput === '') {
            $this->addFlash('error', 'Selectionnez un mois a parser.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $step = trim((string) $request->request->get('step', '10m'));
        if ($step === '') {
            $step = '10m';
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:horizon:parse-canonique',
            '--start=' . $startInput,
            '--months=1',
            '--step=' . $step,
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Parsing echoue.');
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', $message !== '' ? $message : 'Parsing termine.');
        }

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/bulk-month', name: 'admin_horizon_data_bulk_month', methods: ['POST'])]
    public function bulkMonth(Request $request, KernelInterface $kernel, ImportHorizonRepository $repository): Response
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $request->request->get('start', '');
        $start = $this->parseDateTimeInput($startInput, $utc);
        if ($start instanceof \DateTimeInterface) {
            $nextMonthStart = (new \DateTimeImmutable($start->format('Y-m-d H:i:s'), $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        } else {
            $nextMonthStart = $this->resolveNextMonthStartFromImports($repository, $utc);
        }

        $step = trim((string) $request->request->get('step', '10m'));
        if ($step === '') {
            $step = '10m';
        }
        $force = (string) $request->request->get('force', '0') === '1';
        if ($force) {
            [$rangeStart, $rangeStop] = $this->resolveMonthRange($nextMonthStart, $step);
            $repository->deleteRunsOverlappingPeriod(
                null,
                $rangeStart,
                $rangeStop,
                null,
                null
            );
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:horizon:bulk-import',
            '--start=' . $nextMonthStart->format('Y-m-d'),
            '--months=1',
            '--step=' . $step,
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Import mensuel echoue.');
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', $message !== '' ? $message : 'Import mensuel termine.');
        }

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/bulk-year', name: 'admin_horizon_data_bulk_year', methods: ['POST'])]
    public function bulkYear(Request $request, KernelInterface $kernel): Response
    {
        $yearInput = (int) $request->request->get('year', 0);
        if ($yearInput < self::START_YEAR || $yearInput > self::END_YEAR) {
            $this->addFlash('error', 'Annee invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $step = trim((string) $request->request->get('step', self::STEP));
        if ($step === '') {
            $step = self::STEP;
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:horizon:bulk-import',
            '--start=' . sprintf('%04d-01-01', $yearInput),
            '--months=12',
            '--step=' . $step,
        ];

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('error', $message !== '' ? $message : 'Import annuel echoue.');
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('success', $message !== '' ? $message : 'Import annuel termine.');
        }

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/delete-year', name: 'admin_horizon_data_delete_year', methods: ['POST'])]
    public function deleteYear(Request $request, ImportHorizonRepository $repository): Response
    {
        $yearInput = (int) $request->request->get('year', 0);
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_import_year_' . $yearInput, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        if ($yearInput < self::START_YEAR || $yearInput > self::END_YEAR) {
            $this->addFlash('error', 'Annee invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $utc = new \DateTimeZone('UTC');
        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $yearInput), $utc);
        $stop = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $yearInput + 1), $utc);
        $stepSeconds = $this->parseStepToSeconds(self::STEP);
        if ($stepSeconds > 0) {
            $stop = $stop->modify(sprintf('-%d seconds', $stepSeconds));
        }

        $deleted = $repository->deleteRunsOverlappingPeriod(
            null,
            $start,
            $stop,
            null,
            null
        );

        $this->addFlash('success', sprintf('Suppression terminee: %d runs supprimes.', $deleted));

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/delete/{id}', name: 'admin_horizon_data_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        ImportHorizonRepository $repository,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): Response {
        $run = $repository->find($id);
        if (!$run) {
            $this->addFlash('error', 'Run introuvable.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_import_' . $run->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $entityManager->remove($run);
        $entityManager->flush();

        $this->addFlash('success', 'Run supprime.');

        return $this->redirectToRoute('admin_horizon_data');
    }

    #[Route('/admin/horizon_data/delete-all', name: 'admin_horizon_data_delete_all', methods: ['POST'])]
    public function deleteAll(
        Request $request,
        ImportHorizonRepository $repository,
        Connection $connection,
        MonthParseCoverageRepository $coverageRepository
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_all_imports', $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $connection->beginTransaction();
        try {
            $connection->executeStatement('DELETE FROM canonique_data');
            $connection->executeStatement('DELETE FROM import_horizon');
            $coverageRepository->deleteAllForTarget(MonthParseCoverageRepository::TARGET_CANONIQUE_DATA);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Suppression totale echouee: ' . $e->getMessage());
            return $this->redirectToRoute('admin_horizon_data');
        }

        $remaining = $repository->countAll();
        $this->addFlash(
            'success',
            $remaining === 0
                ? 'Toutes les donnees Horizons ont ete supprimees.'
                : 'Suppression terminee, mais des runs restent en base.'
        );

        return $this->redirectToRoute('admin_horizon_data');
    }

    private function parseDateTimeInput(string $input, \DateTimeZone $tz): ?\DateTime
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d',
            \DateTimeInterface::ATOM,
            \DateTimeInterface::RFC3339,
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value, $tz);
            if ($parsed instanceof \DateTime) {
                return $parsed;
            }
        }

        try {
            return new \DateTime($value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveNextMonthStartFromImports(
        ImportHorizonRepository $repository,
        \DateTimeZone $utc
    ): \DateTimeImmutable {
        $maxStopPrimary = $repository->findMaxStopUtc(self::MOON_TARGET, self::CENTER, self::STEP);
        $maxStopFallback = $repository->findMaxStopUtc(self::MOON_TARGET_FALLBACK, self::CENTER, self::STEP);
        $maxStop = $maxStopPrimary;
        if ($maxStopFallback && (!$maxStop || $maxStopFallback > $maxStop)) {
            $maxStop = $maxStopFallback;
        }
        if (!$maxStop) {
            return (new \DateTimeImmutable('now', $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        }

        return (new \DateTimeImmutable($maxStop->format('Y-m-d H:i:s'), $utc))
            ->modify('first day of this month')
            ->setTime(0, 0, 0)
            ->modify('+1 month');
    }

    /**
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable}
     */
    private function resolveMonthRange(\DateTimeImmutable $monthStart, string $step): array
    {
        $stepSeconds = $this->parseStepToSeconds($step);
        $start = $monthStart->modify('first day of this month')->setTime(0, 0, 0);
        $stop = $start->modify('+1 month');
        if ($stepSeconds > 0) {
            $stop = $stop->modify(sprintf('-%d seconds', $stepSeconds));
        }

        return [$start, $stop];
    }

    /**
     * @return array<string, string>
     */
    private function buildMonthCoverage(ImportHorizonRepository $repository, \DateTimeZone $utc): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', self::START_YEAR), $utc);
        $end = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', self::END_YEAR + 1), $utc);

        $moonRuns = array_merge(
            $this->normalizeRuns(
                $repository->findRunsOverlappingPeriod(self::MOON_TARGET, $start, $end, self::CENTER, self::STEP),
                $utc
            ),
            $this->normalizeRuns(
                $repository->findRunsOverlappingPeriod(self::MOON_TARGET_FALLBACK, $start, $end, self::CENTER, self::STEP),
                $utc
            )
        );
        $sunRuns = $this->normalizeRuns(
            $repository->findRunsOverlappingPeriod(self::SUN_TARGET, $start, $end, self::CENTER, self::STEP),
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

                $startTs = $monthStart->getTimestamp();
                $stopTs = $expectedStop->getTimestamp();

                $moonStatus = $this->resolveCoverageStatus($moonRuns, $startTs, $stopTs);
                $sunStatus = $this->resolveCoverageStatus($sunRuns, $startTs, $stopTs);

                $key = $monthStart->format('Y-m');
                if ($moonStatus['full'] && $sunStatus['full']) {
                    $coverage[$key] = 'complete';
                } elseif ($moonStatus['any'] || $sunStatus['any']) {
                    $coverage[$key] = 'partial';
                } else {
                    $coverage[$key] = 'missing';
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
            $hasAny = false;
            $allComplete = true;
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%04d-%02d', $year, $month);
                $status = $monthCoverage[$key] ?? 'missing';
                if ($status !== 'complete') {
                    $allComplete = false;
                }
                if ($status !== 'missing') {
                    $hasAny = true;
                }
            }

            if ($allComplete) {
                $yearCoverage[$year] = 'complete';
            } elseif ($hasAny) {
                $yearCoverage[$year] = 'partial';
            } else {
                $yearCoverage[$year] = 'missing';
            }
        }

        return $yearCoverage;
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
}
