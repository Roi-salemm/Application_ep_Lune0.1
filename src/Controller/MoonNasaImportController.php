<?php

namespace App\Controller;

use App\Repository\MoonNasaImportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

final class MoonNasaImportController extends AbstractController
{
    #[Route('/moon/imports', name: 'moon_imports_index', methods: ['GET'])]
    public function index(MoonNasaImportRepository $repository): Response
    {
        $imports = $repository->findBy([], ['id' => 'DESC']);
        $utc = new \DateTimeZone('UTC');
        $defaultStart = new \DateTime('now', $utc);
        $defaultStart->setTime((int) $defaultStart->format('H'), 0, 0);

        return $this->render('moon_nasa_import/index.html.twig', [
            'imports' => $imports,
            'default_start' => $defaultStart->format('Y-m-d\TH:i'),
            'default_days' => 7,
            'default_step' => '1h',
        ]);
    }

    #[Route('/moon/imports/run', name: 'moon_imports_run', methods: ['POST'])]
    public function run(Request $request, KernelInterface $kernel): Response
    {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $request->request->get('start', '');
        $start = $this->parseDateTimeInput($startInput, $utc);
        if (!$start) {
            $this->addFlash('error', 'Start datetime invalide.');
            return $this->redirectToRoute('moon_imports_index');
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

        return $this->redirectToRoute('moon_imports_index');
    }

    #[Route('/moon/imports/parse', name: 'moon_imports_parse', methods: ['POST'])]
    public function parse(Request $request, KernelInterface $kernel): Response
    {
        $runId = (int) $request->request->get('run_id', 0);
        if ($runId <= 0) {
            $this->addFlash('error', 'Selectionnez un run a parser.');
            return $this->redirectToRoute('moon_imports_index');
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

        return $this->redirectToRoute('moon_imports_index');
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
}
