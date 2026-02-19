<?php

/**
 * Ce fichier definit le DTO de routage pour la reponse IA.
 * Pourquoi : exposer le contexte, la tradition et le mode de selection retenus.
 * Informations specifiques : selection_mode peut etre manual, auto ou fallback.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class RoutingDTO
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'symbolic_weather',
        'symbolic_reading',
        'personal_question',
        'divination_interpretation',
        'non_duality',
        'technical_science',
        'other',
    ])]
    public string $context;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'western_astrology',
        'jyotish',
        'yijing',
        'tarot_marseille',
        'neutral_symbolic',
    ])]
    public string $tradition;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['manual', 'auto', 'fallback'])]
    public string $selection_mode;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1)]
    public float $confidence;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'interpretation',
        'explanation',
        'coaching_request',
        'validation_request',
        'how_to',
        'troubleshooting',
        'other',
    ])]
    public string $user_intent;

    #[Assert\Choice(choices: ['short', 'standard', 'deep'])]
    public ?string $depth_mode = null;

    #[Assert\Choice(choices: ['plain', 'bullet', 'structured'])]
    public ?string $format = null;

    #[Assert\Choice(choices: ['soft', 'neutral', 'direct'])]
    public ?string $tone = null;
}
