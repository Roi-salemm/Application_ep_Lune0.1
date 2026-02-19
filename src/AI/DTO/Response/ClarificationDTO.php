<?php

/**
 * Ce fichier definit la demande de clarification eventuelle.
 * Pourquoi : indiquer si l app doit poser une question de precision.
 * Informations specifiques : question est optionnelle.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class ClarificationDTO
{
    public bool $needs_clarification = false;

    #[Assert\Type('string')]
    public ?string $question = null;
}
