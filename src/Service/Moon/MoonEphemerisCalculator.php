<?php

namespace App\Service\Moon;

final class MoonEphemerisCalculator
{
    private const SYNODIC_MONTH_DAYS = 29.530588;
    private const MOON_RADIUS_KM = 1737.4;

    public function computeAgeDaysFromPhase(?string $phaseDeg): ?float
    {
        if ($phaseDeg === null) {
            return null;
        }

        $numeric = (float) $phaseDeg;
        if ($numeric < 0) {
            return null;
        }

        return ($numeric / 360.0) * self::SYNODIC_MONTH_DAYS;
    }

    public function computeAngularDiameterArcsec(?string $distKm): ?float
    {
        if ($distKm === null) {
            return null;
        }

        $distance = (float) $distKm;
        if ($distance <= 0) {
            return null;
        }

        $angleRad = 2.0 * atan(self::MOON_RADIUS_KM / $distance);
        $angleDeg = rad2deg($angleRad);

        return $angleDeg * 3600.0;
    }
}
