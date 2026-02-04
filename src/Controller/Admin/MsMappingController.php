<?php

namespace App\Controller\Admin;

use App\Repository\MsMappingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controle l'onglet admin ms_mapping.
 * Pourquoi: afficher l'agregation journaliere et declencher le parse mensuel.
 */
final class MsMappingController extends AbstractController
{
    #[Route('/admin/ms_mapping', name: 'admin_ms_mapping', methods: ['GET'])]
    public function index(Request $request, MsMappingRepository $repository): Response
    {
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

        return $this->render('admin/ms_mapping.html.twig', [
            'rows' => $rows,
            'columns' => $columns,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'next_month_start' => $nextMonthStart->format('Y-m-01'),
            'next_month_label' => $this->formatMonthLabel($nextMonthStart),
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
}
