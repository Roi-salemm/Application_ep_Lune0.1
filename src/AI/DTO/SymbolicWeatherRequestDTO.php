<?php

/**
 * Ce fichier definit la requete DTO pour /api/ai/weather.
 * Pourquoi : imposer la presence du snapshot lunaire en v0 pour la meteo symbolique.
 * Informations specifiques : message est optionnel, language est fixe a fr.
 */

namespace App\AI\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class SymbolicWeatherRequestDTO
{
    #[Assert\Type('string')]
    #[Assert\Length(max: 8000)]
    public ?string $message = null;

    #[Assert\NotNull]
    #[Assert\Valid]
    public LunarSnapshotDTO $lunar_snapshot;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fr'])]
    public string $language = 'fr';
}
