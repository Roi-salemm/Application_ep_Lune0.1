<?php

namespace App\Controller\Admin;

use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonNasaImportRepository;
use App\Repository\SolarEphemerisHourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

final class MoonNasaImportController extends AbstractController
{
    #[Route('/admin/horizon_data', name: 'admin_horizon_data', methods: ['GET'])]
    #[Route('/moon/imports', name: 'moon_imports_index', methods: ['GET'])]
    public function index(
        Request $request,
        MoonNasaImportRepository $repository,
        MoonEphemerisHourRepository $moonRepository,
        SolarEphemerisHourRepository $solarRepository
    ): Response
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
        $defaultStart = new \DateTime('now', $utc);
        $defaultStart->setTime((int) $defaultStart->format('H'), 0, 0);

        $moonMonths = $moonRepository->findMonthCoverage();
        $solarMonths = $solarRepository->findMonthCoverage();
        $years = $this->buildYears($moonMonths, $solarMonths, $utc);
        $nextMonthStart = $this->resolveNextMonthStart($moonRepository, $solarRepository, $utc);

        return $this->render('admin/horizon_data.html.twig', [
            'imports' => $imports,
            'default_start' => $defaultStart->format('Y-m-d\TH:i'),
            'default_days' => 7,
            'default_step' => '1h',
            'moon_months' => $moonMonths,
            'solar_months' => $solarMonths,
            'years' => $years,
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
        $step = trim((string) $request->request->get('step', '1h'));
        if ($step === '') {
            $step = '1h';
        }

        $start->setTimezone($utc);
        $startString = $start->format('Y-m-d H:i');

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:import-horizons',
            '--start=' . $startString,
            '--days=' . $days,
            '--step=' . $step,
            '--store-only',
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
        $runId = (int) $request->request->get('run_id', 0);
        if ($runId <= 0) {
            $this->addFlash('error', 'Selectionnez un run a parser.');
            return $this->redirectToRoute('admin_horizon_data');
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:import-horizons',
            '--run-id=' . $runId,
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
    public function bulkMonth(
        Request $request,
        KernelInterface $kernel,
        MoonEphemerisHourRepository $moonRepository,
        SolarEphemerisHourRepository $solarRepository
    ): Response
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $request->request->get('start', '');
        $start = $this->parseDateTimeInput($startInput, $utc);
        if ($start instanceof \DateTimeInterface) {
            $nextMonthStart = (new \DateTimeImmutable($start->format('Y-m-d H:i:s'), $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        } else {
            $nextMonthStart = $this->resolveNextMonthStart($moonRepository, $solarRepository, $utc);
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:ephemeris:bulk-import',
            '--start=' . $nextMonthStart->format('Y-m-d'),
            '--months=1',
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

    #[Route('/admin/horizon_data/delete/{id}', name: 'admin_horizon_data_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        MoonNasaImportRepository $repository,
        MoonEphemerisHourRepository $moonRepository,
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

        $deletedRows = $moonRepository->deleteByRun($run);
        $entityManager->remove($run);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Run supprime (%d lignes associees).', $deletedRows));

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

    /**
     * @param string[] $moonMonths
     * @param string[] $solarMonths
     * @return int[]
     */
    private function buildYears(array $moonMonths, array $solarMonths, \DateTimeZone $utc): array
    {
        $years = [];
        foreach (array_merge($moonMonths, $solarMonths) as $monthKey) {
            if (preg_match('/^(\d{4})-\d{2}$/', $monthKey, $matches) !== 1) {
                continue;
            }
            $years[] = (int) $matches[1];
        }

        if (!$years) {
            $currentYear = (int) (new \DateTime('now', $utc))->format('Y');
            return [$currentYear];
        }

        $minYear = min($years);
        $maxYear = max($years);

        return range($minYear, $maxYear);
    }

    private function resolveNextMonthStart(
        MoonEphemerisHourRepository $moonRepository,
        SolarEphemerisHourRepository $solarRepository,
        \DateTimeZone $utc
    ): \DateTimeImmutable {
        $moonMax = $moonRepository->findMaxTimestamp();
        $solarMax = $solarRepository->findMaxTimestamp();

        if (!$moonMax && !$solarMax) {
            return (new \DateTimeImmutable('now', $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        }

        $reference = $moonMax;
        if ($solarMax && ($reference === null || $solarMax < $reference)) {
            $reference = $solarMax;
        }

        if ($reference === null) {
            return (new \DateTimeImmutable('now', $utc))
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
        }

        return $reference
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
}
