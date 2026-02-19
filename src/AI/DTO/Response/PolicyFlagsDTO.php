<?php

/**
 * Ce fichier definit les flags de politique pour la reponse IA.
 * Pourquoi : isoler les signaux de policy (prompt injection, diagnostic, etc.).
 * Informations specifiques : tous les champs sont booleens et false par defaut.
 */

namespace App\AI\DTO\Response;

final class PolicyFlagsDTO
{
    public bool $prompt_injection = false;
    public bool $proselitism_request = false;
    public bool $diagnosis_request = false;
    public bool $explicit_instruction_request = false;
}
