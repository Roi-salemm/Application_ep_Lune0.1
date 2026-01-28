<?php

namespace App\Service\Moon\Phase;

final class MoonPhaseDefinitions
{
    /**
     * @return array<string, array{angle: float, label: string}>
     */
    public function all(): array
    {
        return [
            'new_moon' => [
                'angle' => 0.0,
                'label' => 'Nouvelle lune',
            ],
            'waxing_crescent' => [
                'angle' => 45.0,
                'label' => 'Premier croissant',
            ],
            'first_quarter' => [
                'angle' => 90.0,
                'label' => 'Premier quartier',
            ],
            'waxing_gibbous' => [
                'angle' => 135.0,
                'label' => 'Gibbeuse croissante',
            ],
            'full_moon' => [
                'angle' => 180.0,
                'label' => 'Pleine lune',
            ],
            'waning_gibbous' => [
                'angle' => 225.0,
                'label' => 'Gibbeuse decroissante',
            ],
            'last_quarter' => [
                'angle' => 270.0,
                'label' => 'Dernier quartier',
            ],
            'waning_crescent' => [
                'angle' => 315.0,
                'label' => 'Dernier croissant',
            ],
        ];
    }
}
