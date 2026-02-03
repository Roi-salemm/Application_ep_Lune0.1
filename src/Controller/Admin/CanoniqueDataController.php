<?php

namespace App\Controller\Admin;

use App\Repository\CanoniqueDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controle l'onglet admin canonique_data.
 * Pourquoi: afficher les colonnes canoniques et declencher le parse mensuel.
 * Infos: lecture directe en SQL pour rester simple hors ORM.
 */
final class CanoniqueDataController extends AbstractController
{
    #[Route('/admin/canonique_data', name: 'admin_canonique_data', methods: ['GET'])]
    public function index(Request $request, CanoniqueDataRepository $repository): Response
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

        return $this->render('admin/canonique_data.html.twig', [
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

    #[Route('/admin/canonique_data/parse-month', name: 'admin_canonique_data_parse_month', methods: ['POST'])]
    public function parseMonth(Request $request, KernelInterface $kernel): Response
    {
        $start = trim((string) $request->request->get('start', ''));
        if ($start === '') {
            $this->addFlash('error', 'Mois invalide.');
            return $this->redirectToRoute('admin_canonique_data');
        }

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:horizon:parse-canonique',
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
            $message = trim($process->getOutput());
            $this->addFlash('success', $message !== '' ? $message : 'Parse termine.');
        }

        return $this->redirectToRoute('admin_canonique_data');
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
            'ts_utc',
            'm1_ra_ast_deg',
            'm1_dec_ast_deg',
            'm2_ra_app_deg',
            'm2_dec_app_deg',
            'm10_illum_frac',
            'm20_range_km',
            'm20_range_rate_km_s',
            'm31_ecl_lon_deg',
            'm31_ecl_lat_deg',
            'm43_pab_lon_deg',
            'm43_pab_lat_deg',
            'm43_phi_deg',
            's31_ecl_lon_deg',
            's31_ecl_lat_deg',
            'm_raw_line',
            's_raw_line',
            'created_at_utc',
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
        CanoniqueDataRepository $repository,
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
}
