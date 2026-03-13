<?php

namespace App\Controller\Admin;

use App\Repository\SwSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ecran admin de lecture de la projection sw_snapshot.
 * Pourquoi: controler rapidement les donnees effectivement envoyables au front.
 * Info: l UI permet tri par colonnes et filtre visuel des lignes de la journee UTC.
 */
final class SwSnapshotController extends AbstractController
{
    #[Route('/admin/sw-snapshot', name: 'admin_sw_snapshot', methods: ['GET'])]
    public function index(SwSnapshotRepository $snapshotRepository): Response
    {
        $utc = new \DateTimeZone('UTC');
        $todayStartUtc = (new \DateTimeImmutable('now', $utc))->setTime(0, 0, 0);
        $todayEndUtc = $todayStartUtc->modify('+1 day');

        return $this->render('admin/sw_snapshot.html.twig', [
            'active_menu' => 'sw_snapshot',
            'page_title' => 'SWSnapshot',
            'page_subtitle' => 'Projection envoyable au front (UTC).',
            'snapshots' => $snapshotRepository->findAllForAdmin(),
            'today_start_ts' => $todayStartUtc->getTimestamp(),
            'today_end_ts' => $todayEndUtc->getTimestamp(),
            'today_label' => $todayStartUtc->format('Y-m-d'),
        ]);
    }
}

