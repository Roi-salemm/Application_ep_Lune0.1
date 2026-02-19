<?php

/**
 * Ce fichier definit la requete DTO pour /api/ai/chat (v1).
 * Pourquoi : cadrer la structure d entree pour le chat multi contextes avec validation Symfony.
 * Informations specifiques : context et tradition peuvent etre null pour la detection auto.
 */

namespace App\AI\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AiChatRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(min: 1, max: 8000)]
    public string $message;

    // null => detection auto en V1
    #[Assert\Choice(choices: [
        'symbolic_weather',
        'symbolic_reading',
        'personal_question',
        'divination_interpretation',
        'non_duality',
        'technical_science',
        'other',
    ])]
    public ?string $context = null;

    // null => detection auto en V1
    #[Assert\Choice(choices: [
        'western_astrology',
        'jyotish',
        'yijing',
        'tarot_marseille',
        'neutral_symbolic',
    ])]
    public ?string $tradition = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fr'])]
    public string $language = 'fr';

    #[Assert\Choice(choices: ['short', 'standard', 'deep'])]
    public ?string $depth_mode = null;

    #[Assert\Choice(choices: ['soft', 'neutral', 'direct'])]
    public ?string $tone = null;

    #[Assert\Choice(choices: ['plain', 'bullet', 'structured'])]
    public ?string $format = null;

    // optionnel (utile surtout pour symbolic_weather)
    #[Assert\Valid]
    public ?LunarSnapshotDTO $lunar_snapshot = null;

    /** @var ChatMessageDTO[] */
    #[Assert\Type('array')]
    #[Assert\Valid]
    public array $history = [];
}
