<?php

/**
 * Ce fichier definit l action de safety pour la reponse IA.
 * Pourquoi : permettre un mode de reponse clair (allow, soft_refusal, refusal, safety_redirect).
 * Informations specifiques : reason reste une chaine libre.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class SafetyActionDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['allow', 'soft_refusal', 'refusal', 'safety_redirect'])]
    public string $mode = 'allow';

    #[Assert\Type('string')]
    public string $reason = '';
}
