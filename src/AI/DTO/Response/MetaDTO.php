<?php

/**
 * Ce fichier definit le DTO meta pour la reponse IA.
 * Pourquoi : fournir les informations de version, langue et request_id pour l audit.
 * Informations specifiques : language est limite a fr selon la spec.
 */

namespace App\AI\DTO\Response;

use Symfony\Component\Validator\Constraints as Assert;

final class MetaDTO
{
    #[Assert\NotBlank]
    public string $schema_version;

    #[Assert\NotBlank]
    public string $created_at_utc;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fr'])]
    public string $language = 'fr';

    #[Assert\NotBlank]
    public string $request_id;
}
