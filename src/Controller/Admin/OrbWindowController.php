<?php

namespace App\Controller\Admin;

use App\Repository\MsMappingRepository;
use App\Repository\OrbWindowRepository;
use App\Service\Moon\OrbWindowParseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controle l'onglet admin Orb Window.
 * Pourquoi: visualiser la couverture mensuelle et declencher le parse de fenetres calculees vers orb_window.
 * Info: toutes les bornes de parse sont manipulees en UTC.
 */
final class OrbWindowController extends AbstractController
{
    private const START_YEAR = 1920;
    private const END_YEAR = 2150;

    #[Route('/admin/orb-window', name: 'admin_orb_window', methods: ['GET'])]
    public function index(
        Request $request,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository,
        OrbWindowParseService $orbWindowParseService
    ): Response {
        $family = $this->sanitizeFamily((string) $request->query->get('family', OrbWindowParseService::FAMILY_INFLUENCE_ORB));
        $method = $orbWindowParseService->resolveCalculationMethod($family);

        $sourceCoverage = array_fill_keys(
            $msMappingRepository->findPhaseEventMonthCoverage(self::START_YEAR, self::END_YEAR),
            true
        );
        $parsedCoverage = $method !== null
            ? array_fill_keys(
                $orbWindowRepository->findMonthCoverageByFamilyAndMethod($family, $method, self::START_YEAR, self::END_YEAR),
                true
            )
            : [];

        $monthCoverage = $this->buildMonthCoverage($sourceCoverage, $parsedCoverage);
        $yearCoverage = $this->buildYearCoverage($monthCoverage);

        return $this->render('admin/orb_window.html.twig', [
            'active_menu' => 'orb_window',
            'page_title' => 'Orb Window',
            'page_subtitle' => 'Parsing des fenetres calculees autour des phases lunaires (UTC).',
            'window_family' => $family,
            'family_options' => $orbWindowParseService->supportedFamilies(),
            'calculation_method' => $method,
            'method_available' => $method !== null,
            'calendar_years' => range(self::START_YEAR, self::END_YEAR),
            'month_coverage' => $monthCoverage,
            'year_coverage' => $yearCoverage,
        ]);
    }

    #[Route('/admin/orb-window/parse-month', name: 'admin_orb_window_parse_month', methods: ['POST'])]
    public function parseMonth(
        Request $request,
        EntityManagerInterface $entityManager,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository,
        OrbWindowParseService $orbWindowParseService
    ): RedirectResponse {
        $family = $this->sanitizeFamily((string) $request->request->get('window_family', ''));
        $startRaw = trim((string) $request->request->get('start', ''));
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('parse_orb_window_month', $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
        }

        $startUtc = $this->parseMonthStart($startRaw);
        if (!$startUtc) {
            $this->addFlash('error', 'Mois invalide.');
            return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
        }

        try {
            $result = $orbWindowParseService->parseRange(
                $family,
                $startUtc,
                $startUtc->modify('+1 month'),
                $entityManager,
                $msMappingRepository,
                $orbWindowRepository
            );
            $this->addFlash(
                'success',
                sprintf(
                    'Parse %s %s: %d cree(s), %d supprime(s), %d evenement(s) source.',
                    $result['window_family'],
                    $startUtc->format('Y-m'),
                    $result['created'],
                    $result['deleted'],
                    $result['source_events']
                )
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
    }

    #[Route('/admin/orb-window/parse-year', name: 'admin_orb_window_parse_year', methods: ['POST'])]
    public function parseYear(
        Request $request,
        EntityManagerInterface $entityManager,
        MsMappingRepository $msMappingRepository,
        OrbWindowRepository $orbWindowRepository,
        OrbWindowParseService $orbWindowParseService
    ): RedirectResponse {
        $family = $this->sanitizeFamily((string) $request->request->get('window_family', ''));
        $year = (int) $request->request->get('year', 0);
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('parse_orb_window_year', $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
        }
        if ($year < self::START_YEAR || $year > self::END_YEAR) {
            $this->addFlash('error', 'Annee invalide.');
            return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
        }

        $utc = new \DateTimeZone('UTC');
        $startUtc = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year), $utc);
        $endUtc = $startUtc->modify('+1 year');

        try {
            $result = $orbWindowParseService->parseRange(
                $family,
                $startUtc,
                $endUtc,
                $entityManager,
                $msMappingRepository,
                $orbWindowRepository
            );
            $this->addFlash(
                'success',
                sprintf(
                    'Parse %s %d: %d cree(s), %d supprime(s), %d evenement(s) source.',
                    $result['window_family'],
                    $year,
                    $result['created'],
                    $result['deleted'],
                    $result['source_events']
                )
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_orb_window', ['family' => $family]);
    }

    /**
     * @param array<string, bool> $sourceCoverage
     * @param array<string, bool> $parsedCoverage
     * @return array<string, string>
     */
    private function buildMonthCoverage(array $sourceCoverage, array $parsedCoverage): array
    {
        $coverage = [];
        for ($year = self::START_YEAR; $year <= self::END_YEAR; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%04d-%02d', $year, $month);
                if (isset($parsedCoverage[$key])) {
                    $coverage[$key] = 'parsed';
                    continue;
                }
                if (isset($sourceCoverage[$key])) {
                    $coverage[$key] = 'ready';
                    continue;
                }
                $coverage[$key] = 'missing';
            }
        }

        return $coverage;
    }

    /**
     * @param array<string, string> $monthCoverage
     * @return array<int, string>
     */
    private function buildYearCoverage(array $monthCoverage): array
    {
        $years = [];
        for ($year = self::START_YEAR; $year <= self::END_YEAR; $year++) {
            $parsedCount = 0;
            $readyCount = 0;
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%04d-%02d', $year, $month);
                $status = $monthCoverage[$key] ?? 'missing';
                if ($status === 'parsed') {
                    $parsedCount++;
                    continue;
                }
                if ($status === 'ready') {
                    $readyCount++;
                }
            }

            if ($parsedCount === 12) {
                $years[$year] = 'parsed';
            } elseif ($parsedCount > 0) {
                $years[$year] = 'partial';
            } elseif ($readyCount > 0) {
                $years[$year] = 'ready';
            } else {
                $years[$year] = 'missing';
            }
        }

        return $years;
    }

    private function parseMonthStart(string $raw): ?\DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value, $utc);
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return $date->setTimezone($utc)->setTime(0, 0, 0);
    }

    private function sanitizeFamily(string $family): string
    {
        $value = strtolower(trim($family));
        return $value !== '' ? $value : OrbWindowParseService::FAMILY_INFLUENCE_ORB;
    }
}
