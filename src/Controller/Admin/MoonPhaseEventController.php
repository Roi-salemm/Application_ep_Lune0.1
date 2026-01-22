<?php

namespace App\Controller\Admin;

use App\Repository\MoonPhaseEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoonPhaseEventController extends AbstractController
{
    #[Route('/admin/moon_phase_events', name: 'admin_moon_phase_events', methods: ['GET'])]
    public function index(MoonPhaseEventRepository $repository): Response
    {
        $limit = 200;
        $events = $repository->findLatest($limit);

        return $this->render('admin/moon_phase_events.html.twig', [
            'events' => $events,
            'limit' => $limit,
        ]);
    }
}
