<?php

/**
 * Ce fichier definit le DTO de reponse IA complet.
 * Pourquoi : regrouper toutes les sections du JSON final et permettre la validation globale.
 * Informations specifiques : glossary_and_definitions est optionnel, assumptions est limite a 500 caracteres.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class AiResponseDTO
{
    #[Assert\Valid]
    public MetaDTO $meta;

    #[Assert\Valid]
    public RoutingDTO $routing;

    #[Assert\Valid]
    public SafetyAndLimitsDTO $safety_and_limits;

    #[Assert\Valid]
    public ContentDTO $content;

    #[Assert\Valid]
    public DataUsedDTO $data_used;

    /** @var GlossaryItemDTO[] */
    #[Assert\Type('array')]
    #[Assert\Valid]
    public array $glossary_and_definitions = [];

    #[Assert\Type('string')]
    #[Assert\Length(max: 500)]
    public string $assumptions = '';

    #[Assert\Valid]
    public ClarificationDTO $clarification;

    #[Assert\Valid]
    public StyleContractDTO $style_contract;
}
