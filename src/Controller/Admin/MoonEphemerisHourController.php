<?php

namespace App\Controller\Admin;

use App\Repository\MoonEphemerisHourRepository;
use App\Service\Ephemeris\EphemerisCoverageVerifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoonEphemerisHourController extends AbstractController
{
    #[Route('/admin/moon_ephemeris_hours', name: 'admin_moon_ephemeris_hours', methods: ['GET'])]
    public function index(MoonEphemerisHourRepository $repository): Response
    {
        return $this->renderIndex($repository);
    }

    #[Route('/admin/moon_ephemeris_hours/verify', name: 'admin_moon_ephemeris_hours_verify', methods: ['POST'])]
    public function verify(
        Request $request,
        MoonEphemerisHourRepository $repository,
        EphemerisCoverageVerifierService $verifier
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('verify_moon_ephemeris', $token)) {
            $this->addFlash('verification_error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_moon_ephemeris_hours');
        }

        try {
            $result = $verifier->verifyTable('moon_ephemeris_hour', 'Moon', 3600, 20);
            $report = $verifier->formatReport($result);
        } catch (\Throwable $e) {
            $this->addFlash('verification_error', 'Verification echouee: ' . $e->getMessage());
            return $this->redirectToRoute('admin_moon_ephemeris_hours');
        }

        $this->addFlash('verification_report', $report);

        return $this->redirectToRoute('admin_moon_ephemeris_hours');
    }

    private function renderIndex(MoonEphemerisHourRepository $repository): Response
    {
        $limit = 200;
        $hours = $repository->findLatest($limit);

        $payload = [
            'hours' => $hours,
            'limit' => $limit,
        ];

        return $this->render('admin/moon_ephemeris_hours.html.twig', $payload);
    }
}
