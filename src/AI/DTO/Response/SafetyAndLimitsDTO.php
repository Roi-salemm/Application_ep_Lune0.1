<?php

/**
 * Ce fichier definit le bloc safety_and_limits de la reponse IA.
 * Pourquoi : regrouper sensibilite, flags et action dans une structure unique.
 * Informations specifiques : sensitivity est limitee a low/medium/high.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class SafetyAndLimitsDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['low', 'medium', 'high'])]
    public string $sensitivity = 'low';

    #[Assert\Valid]
    public RiskFlagsDTO $risk_flags;

    #[Assert\Valid]
    public PolicyFlagsDTO $policy_flags;

    #[Assert\Valid]
    public SafetyActionDTO $action;

    #[Assert\Type('string')]
    public string $note = '';
}
