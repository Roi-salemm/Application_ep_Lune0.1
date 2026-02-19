<?php

/**
 * Ce fichier definit les flags de risque pour la reponse IA.
 * Pourquoi : representer les signaux de risque de facon explicite.
 * Informations specifiques : tous les champs sont booleens et false par defaut.
 */

namespace App\AI\DTO\Response;

final class RiskFlagsDTO
{
    public bool $medical = false;
    public bool $legal = false;
    public bool $financial = false;
    public bool $self_harm = false;
    public bool $violence = false;
    public bool $hate = false;
    public bool $sexual_content = false;
    public bool $minors = false;
}
