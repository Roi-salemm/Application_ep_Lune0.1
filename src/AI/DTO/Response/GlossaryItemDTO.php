<?php

/**
 * Ce fichier definit un item de glossaire pour la reponse IA.
 * Pourquoi : fournir des definitions optionnelles et concises.
 * Informations specifiques : definition est limitee en longueur et tradition est controlee.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class GlossaryItemDTO
{
    #[Assert\NotBlank]
    public string $term;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 280)]
    public string $definition;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'western_astrology',
        'jyotish',
        'yijing',
        'tarot_marseille',
        'neutral_symbolic',
    ])]
    public string $tradition;
}
