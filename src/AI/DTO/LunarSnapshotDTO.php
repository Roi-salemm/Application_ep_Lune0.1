<?php

/**
 * Ce fichier definit le DTO de snapshot lunaire utilise en entree.
 * Pourquoi : centraliser les champs requis pour les requetes qui utilisent les donnees lunaires.
 * Informations specifiques : moon_phase, moon_sign et moon_degree sont obligatoires selon la spec.
 */

namespace App\AI\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class LunarSnapshotDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $moon_phase;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $moon_sign;

    #[Assert\NotNull]
    #[Assert\Type('numeric')]
    public float $moon_degree;
}
