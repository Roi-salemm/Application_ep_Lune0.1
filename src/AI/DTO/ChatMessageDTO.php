<?php

/**
 * Ce fichier definit un message de chat pour l historique.
 * Pourquoi : representer un tour de conversation avec role et contenu valides.
 * Informations specifiques : role est limite a user/assistant et content a une longueur maximale.
 */

namespace App\AI\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ChatMessageDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['user', 'assistant'])]
    public string $role;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(min: 1, max: 6000)]
    public string $content;
}
