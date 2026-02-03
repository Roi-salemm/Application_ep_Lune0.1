<?php

namespace App\Controller\Admin;

use App\Repository\MoonEphemerisHourRepository;
use App\Service\Ephemeris\EphemerisCoverageVerifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
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

        $stepInput = trim((string) $request->request->get('step', '1h'));
        $stepSeconds = $this->parseStepToSeconds($stepInput);
        if ($stepSeconds <= 0) {
            $this->addFlash('verification_error', 'Pas attendu invalide.');
            return $this->redirectToRoute('admin_moon_ephemeris_hours');
        }

        try {
            $result = $verifier->verifyTable('moon_ephemeris_hour', 'Moon', $stepSeconds, 20);
            $report = $verifier->formatReport($result);
        } catch (\Throwable $e) {
            $this->addFlash('verification_error', 'Verification echouee: ' . $e->getMessage());
            return $this->redirectToRoute('admin_moon_ephemeris_hours');
        }

        $this->addFlash('verification_report', $report);

        return $this->redirectToRoute('admin_moon_ephemeris_hours');
    }

    #[Route('/admin/moon_ephemeris_hours/parse-raw', name: 'admin_moon_ephemeris_hours_parse_raw', methods: ['POST'])]
    public function parseRaw(Request $request, KernelInterface $kernel): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('parse_moon_ephemeris_raw', $token)) {
            $this->addFlash('parse_raw_error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_moon_ephemeris_hours');
        }

        $runId = (int) $request->request->get('run_id', 0);

        $command = [
            'php',
            $kernel->getProjectDir() . '/bin/console',
            'app:moon:parse-raw',
        ];

        if ($runId > 0) {
            $command[] = '--run-id=' . $runId;
        }

        $process = new Process($command, $kernel->getProjectDir());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->addFlash('parse_raw_error', 'Parsing raw echoue.');
            if ($message !== '') {
                $this->addFlash('parse_raw_output', $message);
            }
        } else {
            $message = trim($process->getOutput());
            $this->addFlash('parse_raw_success', 'Parsing raw termine.');
            if ($message !== '') {
                $this->addFlash('parse_raw_output', $message);
            }
        }

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
