<?php

namespace App\Controller\Api;

use App\Repository\MoonEphemerisHourRepository;
use App\Repository\MoonPhaseEventRepository;
use App\Service\Moon\Phase\MoonPhaseLabeler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/moon')]
final class MoonApiController extends AbstractController
{
    #[Route('/ephemeris', name: 'api_moon_ephemeris', methods: ['GET', 'OPTIONS'])]
    public function ephemeris(Request $request, MoonEphemerisHourRepository $repository): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        $startParam = (string) $request->query->get('start', '');
        $endParam = (string) $request->query->get('end', '');

        if ($startParam === '' || $endParam === '') {
            return new JsonResponse(
                ['error' => 'start and end query parameters are required.'],
                400,
                $this->corsHeaders()
            );
        }

        $utc = new \DateTimeZone('UTC');

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
            $timestamp = $row->getTsUtc();
            if (!$timestamp instanceof \DateTimeInterface) {
                continue;
            }

            $items[] = [
                'ts_utc' => $timestamp->format('Y-m-d H:i:s'),
                'phase_deg' => $row->getPhaseDeg(),
                'illum_pct' => $row->getIllumPct(),
                'age_days' => $row->getAgeDays(),
                'diam_km' => $row->getDiamKm(),
                'dist_km' => $row->getDistKm(),
                'ra_hours' => $row->getRaHours(),
                'dec_deg' => $row->getDecDeg(),
                'slon_deg' => $row->getSlonDeg(),
                'slat_deg' => $row->getSlatDeg(),
                'sub_obs_lon_deg' => $row->getSubObsLonDeg(),
                'sub_obs_lat_deg' => $row->getSubObsLatDeg(),
                'elon_deg' => $row->getElonDeg(),
                'elat_deg' => $row->getElatDeg(),
                'axis_a_deg' => $row->getAxisADeg(),
                'delta_au' => $row->getDeltaAu(),
                'deldot_km_s' => $row->getDeldotKmS(),
                'sun_elong_deg' => $row->getSunElongDeg(),
                'sun_target_obs_deg' => $row->getSunTargetObsDeg(),
                'sun_ra_hours' => $row->getSunRaHours(),
                'sun_dec_deg' => $row->getSunDecDeg(),
                'sun_ecl_lon_deg' => $row->getSunEclLonDeg(),
                'sun_ecl_lat_deg' => $row->getSunEclLatDeg(),
                'sun_dist_au' => $row->getSunDistAu(),
                'sun_trail' => $row->getSunTrail(),
                'constellation' => $row->getConstellation(),
                'delta_t_sec' => $row->getDeltaTSec(),
                'dut1_sec' => $row->getDut1Sec(),
                'pressure_hpa' => $row->getPressureHpa(),
                'temperature_c' => $row->getTemperatureC(),
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

    #[Route('/phase/current', name: 'api_moon_phase_current', methods: ['GET', 'OPTIONS'])]
    public function currentPhase(
        Request $request,
        MoonEphemerisHourRepository $repository,
        MoonPhaseLabeler $labeler
    ): JsonResponse {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        $utc = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $utc);
        $row = $repository->findLatestAtOrBefore($now);

        if ($row === null) {
            return new JsonResponse(
                ['phaseLabel' => null, 'asOf' => null],
                404,
                $this->corsHeaders()
            );
        }

        $phaseDeg = $row->getPhaseDeg() ?? $row->getSunTargetObsDeg();
        $phaseLabel = $labeler->labelForPhaseDeg($phaseDeg) ?? 'Phase lunaire';
        $percentage = null;

        if ($phaseDeg !== null) {
            $angle = deg2rad((float) $phaseDeg);
            $illumination = (1.0 - cos($angle)) / 2.0;
            $percentage = round($illumination * 100.0, 1);
        }

        return new JsonResponse(
            [
                'phaseLabel' => $phaseLabel,
                'percentage' => $percentage,
                'asOf' => $row->getTsUtc()?->format('Y-m-d H:i:s'),
            ],
            200,
            $this->corsHeaders()
        );
    }

    #[Route('/phase-events', name: 'api_moon_phase_events', methods: ['GET', 'OPTIONS'])]
    public function phaseEvents(
        Request $request,
        MoonPhaseEventRepository $phaseEventRepository
    ): JsonResponse {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        $startParam = (string) $request->query->get('start', '');
        $endParam = (string) $request->query->get('end', '');

        if ($startParam === '' || $endParam === '') {
            return new JsonResponse(
                ['error' => 'start and end query parameters are required.'],
                400,
                $this->corsHeaders()
            );
        }

        $utc = new \DateTimeZone('UTC');

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

        $start = $start->setTimezone($utc);
        $end = $end->setTimezone($utc);

        if ($end < $start) {
            return new JsonResponse(
                ['error' => 'end must be greater than or equal to start.'],
                400,
                $this->corsHeaders()
            );
        }

        $eventRows = $phaseEventRepository->findByTimestampRange($start, $end);
        $events = [];
        foreach ($eventRows as $event) {
            $ts = $event->getTsUtc();
            $displayAt = $event->getDisplayAtUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }

            $events[] = [
                'ts_utc' => $ts->format('Y-m-d H:i:s'),
                'display_at_utc' => $displayAt?->format('Y-m-d H:i:s'),
                'event_type' => $event->getEventType(),
                'phase_name' => $event->getPhaseName(),
                'phase_deg' => $event->getPhaseDeg(),
                'illum_pct' => $event->getIllumPct(),
                'precision_sec' => $event->getPrecisionSec(),
                'source' => $event->getSource(),
            ];
        }

        return new JsonResponse(
            [
                'range' => [
                    'start_utc' => $start->format('Y-m-d H:i:s'),
                    'end_utc' => $end->format('Y-m-d H:i:s'),
                ],
                'phase_event_count' => count($events),
                'phase_events' => $events,
            ],
            200,
            $this->corsHeaders()
        );
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
