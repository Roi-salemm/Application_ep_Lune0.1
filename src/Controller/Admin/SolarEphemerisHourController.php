<?php

namespace App\Controller\Admin;

use App\Repository\SolarEphemerisHourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SolarEphemerisHourController extends AbstractController
{
    #[Route('/admin/solar_ephemeris_hours', name: 'admin_solar_ephemeris_hours', methods: ['GET'])]
    public function index(SolarEphemerisHourRepository $repository): Response
    {
        $limit = 200;
        $hours = $repository->findLatest($limit);

        return $this->render('admin/solar_ephemeris_hours.html.twig', [
            'hours' => $hours,
            'limit' => $limit,
        ]);
    }
}
