<?php

/**
 * Ce fichier definit les donnees utilisees pour la generation.
 * Pourquoi : regrouper le snapshot lunaire et les indicateurs d entree.
 * Informations specifiques : lunar_snapshot est optionnel, inputs_present est requis.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class DataUsedDTO
{
    #[Assert\Valid]
    public ?LunarSnapshotUsedDTO $lunar_snapshot = null;

    #[Assert\Valid]
    public InputsPresentDTO $inputs_present;
}
