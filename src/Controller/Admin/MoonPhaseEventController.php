<?php

namespace App\Controller\Admin;

use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonPhaseEventRepository;
use App\Service\Moon\Phase\MoonPhaseEventCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoonPhaseEventController extends AbstractController
{
    #[Route('/admin/moon_phase_events', name: 'admin_moon_phase_events', methods: ['GET'])]
    public function index(MoonPhaseEventRepository $repository): Response
    {
        $limit = 200;
        $events = $repository->findLatest($limit);
        $utc = new \DateTimeZone('UTC');
        $phaseMonths = $repository->findMonthCoverage();
        $years = $this->buildYears($phaseMonths, $utc);
        $nextMonthStart = $this->resolveNextMonthStart($repository, $utc);

        return $this->render('admin/moon_phase_events.html.twig', [
            'events' => $events,
            'limit' => $limit,
            'phase_months' => $phaseMonths,
            'years' => $years,
            'next_month_label' => $this->formatMonthLabel($nextMonthStart),
            'next_month_start' => $nextMonthStart->format('Y-m-01'),
        ]);
    }

    #[Route('/admin/moon_phase_events/compute-month', name: 'admin_moon_phase_events_compute_month', methods: ['POST'])]
    public function computeMonth(
        Request $request,
        MoonPhaseEventCalculatorService $calculatorService,
        MoonEphemerisHourRepository $moonRepository
    ): Response {
        $utc = new \DateTimeZone('UTC');
        $startInput = (string) $request->request->get('start', '');
        $monthStart = $this->parseMonthInput($startInput, $utc);
        if (!$monthStart) {
            $this->addFlash('error', 'Mois invalide.');
            return $this->redirectToRoute('admin_moon_phase_events');
        }

        $monthStart = $monthStart->setTime(0, 0, 0)->modify('first day of this month');
        $monthStop = $monthStart->modify('+1 month');
        $monthLabel = $this->formatMonthLabel($monthStart);

        $moonCount = $moonRepository->countByTimestampRange($monthStart, $monthStop);

        if ($moonCount === 0) {
            $missing = [];
            if ($moonCount === 0) {
                $missing[] = 'Moon';
            }
            $this->addFlash(
                'error',
                sprintf('Donnees %s manquantes pour %s.', implode(' + ', $missing), $monthLabel)
            );
            return $this->redirectToRoute('admin_moon_phase_events');
        }

        $result = $calculatorService->calculateAndPersistMonth($monthStart, $monthStop, $utc);

        $this->addFlash(
            'success',
            sprintf(
                'Phases calculees pour %s: %d total, %d ajoutees, %d maj.',
                $monthLabel,
                $result['total'],
                $result['saved'],
                $result['updated']
            )
        );

        return $this->redirectToRoute('admin_moon_phase_events');
    }

    /**
     * @param string[] $phaseMonths
     * @return int[]
     */
    private function buildYears(array $phaseMonths, \DateTimeZone $utc): array
    {
        $years = [];
        foreach ($phaseMonths as $monthKey) {
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

    private function resolveNextMonthStart(MoonPhaseEventRepository $repository, \DateTimeZone $utc): \DateTimeImmutable
    {
        $max = $repository->findMaxTimestamp();
        if ($max === null) {
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

    private function parseMonthInput(string $input, \DateTimeZone $utc): ?\DateTimeImmutable
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d',
            'Y-m',
            'Y-m-d H:i',
            'Y-m-d\TH:i',
            \DateTimeInterface::ATOM,
            \DateTimeInterface::RFC3339,
        ];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value, $utc);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        try {
            return new \DateTimeImmutable($value, $utc);
        } catch (\Throwable) {
            return null;
        }
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
