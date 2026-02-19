<?php

/**
 * Ce fichier definit les indicateurs d entree presents dans la requete.
 * Pourquoi : tracer si message, historique ou snapshot ont ete utilises.
 * Informations specifiques : valeurs par defaut alignes sur la spec.
 */

namespace App\AI\DTO\Response;

final class InputsPresentDTO
{
    public bool $user_message = true;
    public bool $history = false;
    public bool $lunar_snapshot = false;
}
