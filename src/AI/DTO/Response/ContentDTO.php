<?php

/**
 * Ce fichier definit le contenu principal de la reponse IA.
 * Pourquoi : separer title, text et formats additionnels (bullets, sections).
 * Informations specifiques : title et text ont des limites de longueur.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class ContentDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 120)]
    public string $title;

    #[Assert\NotBlank]
    #[Assert\Length(min: 50, max: 1200)]
    public string $text;

    /** @var string[] */
    #[Assert\Type('array')]
    public array $bullets = [];

    /** @var array<int, array{title?: string, text?: string, bullets?: array}> */
    #[Assert\Type('array')]
    public array $sections = [];
}
