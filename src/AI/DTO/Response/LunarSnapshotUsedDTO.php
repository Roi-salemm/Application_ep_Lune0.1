<?php

/**
 * Ce fichier definit le snapshot lunaire utilise dans la reponse.
 * Pourquoi : exposer les donnees lunaires effectivement prises en compte.
 * Informations specifiques : les trois champs sont obligatoires.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class LunarSnapshotUsedDTO
{
    #[Assert\NotBlank]
    public string $moon_phase;

    #[Assert\NotBlank]
    public string $moon_sign;

    #[Assert\NotNull]
    #[Assert\Type('numeric')]
    public float $moon_degree;
}
