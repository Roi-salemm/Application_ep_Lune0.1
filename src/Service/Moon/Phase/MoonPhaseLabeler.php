<?php

namespace App\Service\Moon\Phase;

final class MoonPhaseLabeler
{
    /**
     * @return string|null
     */
    public function labelForPhaseDeg(?string $phaseDeg): ?string
    {
        if ($phaseDeg === null) {
            return null;
        }

        $deg = (float) $phaseDeg;
        if ($deg < 0) {
            return null;
        }

        $deg = fmod($deg, 360.0);
        if ($deg < 0) {
            $deg += 360.0;
        }

        if ($deg < 22.5 || $deg >= 337.5) {
            return 'Nouvelle lune';
        }
        if ($deg < 67.5) {
            return 'Premier croissant';
        }
        if ($deg < 112.5) {
            return 'Premier quartier';
        }
        if ($deg < 157.5) {
            return 'Gibbeuse croissante';
        }
        if ($deg < 202.5) {
            return 'Pleine lune';
        }
        if ($deg < 247.5) {
            return 'Gibbeuse décroissante';
        }
        if ($deg < 292.5) {
            return 'Dernier quartier';
        }

        return 'Dernier croissant';
    }
}
