<?php

/**
 * Expose les donnees canonique_data pour l'app mobile.
 * Pourquoi: fournir les colonnes geocentriques sans transmettre les raw_line.
 * Infos: start/end obligatoires, reponse en UTC.
 */

namespace App\Controller\Api;

use App\Repository\CanoniqueDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/moon')]
final class CanoniqueDataApiController extends AbstractController
{
    #[Route('/canonique', name: 'api_moon_canonique', methods: ['GET', 'OPTIONS'])]
    public function canonique(Request $request, CanoniqueDataRepository $repository): JsonResponse
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
                'ts_utc' => $this->normalizeDateValue($row['ts_utc'] ?? null),
                'm1_ra_ast_deg' => $row['m1_ra_ast_deg'] ?? null,
                'm1_dec_ast_deg' => $row['m1_dec_ast_deg'] ?? null,
                'm2_ra_app_deg' => $row['m2_ra_app_deg'] ?? null,
                'm2_dec_app_deg' => $row['m2_dec_app_deg'] ?? null,
                'm10_illum_frac' => $row['m10_illum_frac'] ?? null,
                'm20_range_km' => $row['m20_range_km'] ?? null,
                'm20_range_rate_km_s' => $row['m20_range_rate_km_s'] ?? null,
                'm31_ecl_lon_deg' => $row['m31_ecl_lon_deg'] ?? null,
                'm31_ecl_lat_deg' => $row['m31_ecl_lat_deg'] ?? null,
                'm43_pab_lon_deg' => $row['m43_pab_lon_deg'] ?? null,
                'm43_pab_lat_deg' => $row['m43_pab_lat_deg'] ?? null,
                'm43_phi_deg' => $row['m43_phi_deg'] ?? null,
                's31_ecl_lon_deg' => $row['s31_ecl_lon_deg'] ?? null,
                's31_ecl_lat_deg' => $row['s31_ecl_lat_deg'] ?? null,
                'created_at_utc' => $this->normalizeDateValue($row['created_at_utc'] ?? null),
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

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
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
