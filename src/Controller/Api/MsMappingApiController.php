<?php

/**
 * Expose ms_mapping pour l app mobile.
 * Pourquoi: fournir un dataset journalier simple sur 3 mois.
 * Infos: start/end optionnels (par defaut now +/- 45 jours).
 */

namespace App\Controller\Api;

use App\Repository\MsMappingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/moon')]
final class MsMappingApiController extends AbstractController
{
    #[Route('/ms_mapping', name: 'api_moon_ms_mapping', methods: ['GET', 'OPTIONS'])]
    public function msMapping(Request $request, MsMappingRepository $repository): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        $utc = new \DateTimeZone('UTC');
        $startParam = (string) $request->query->get('start', '');
        $endParam = (string) $request->query->get('end', '');

        if ($startParam === '' && $endParam === '') {
            $now = new \DateTimeImmutable('now', $utc);
            $start = $now->modify('-45 days');
            $end = $now->modify('+45 days');
        } else {
            if ($startParam === '' || $endParam === '') {
                return new JsonResponse(
                    ['error' => 'start and end query parameters are required (or omit both for default range).'],
                    400,
                    $this->corsHeaders()
                );
            }

            try {
                $start = new \DateTimeImmutable($startParam, $utc);
                $end = new \DateTimeImmutable($endParam, $utc);
            } catch (\Throwable) {
                return new JsonResponse(
                    ['error' => 'Invalid date format. Expected ISO date or YYYY-MM-DD HH:MM:SS.'],
                    400,
                    $this->corsHeaders()
                );
            }
        }

        $start = $start->setTimezone($utc);
        $end = $end->setTimezone($utc);

        if ($end < $start) {
            return new JsonResponse(
                ['error' => 'end must be greater than or equal to start.'],
                400,
                $this->corsHeaders()
            );
        }

        $rows = $repository->findByTimestampRange($start, $end);
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => $row->getId(),
                'ts_utc' => $this->formatDate($row->getTsUtc()),
                'm43_pab_lon_deg' => $row->getM43PabLonDeg(),
                'm10_illum_frac' => $row->getM10IllumFrac(),
                'm31_ecl_lon_deg' => $row->getM31EclLonDeg(),
                's31_ecl_lon_deg' => $row->getS31EclLonDeg(),
                'phase' => $row->getPhase(),
                'phase_hour' => $this->formatDate($row->getPhaseHour()),
            ];
        }

        return new JsonResponse(
            [
                'count' => count($items),
                'items' => $items,
            ],
            200,
            $this->corsHeaders()
        );
    }

    private function formatDate(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];
    }
}
