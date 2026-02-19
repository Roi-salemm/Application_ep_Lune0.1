<?php

/**
 * Ce fichier definit le contrat de style declare par le modele.
 * Pourquoi : exposer les garanties de style que Symfony doit aussi verifier.
 * Informations specifiques : toutes les valeurs sont booleennes.
 */

namespace App\AI\DTO\Response;

final class StyleContractDTO
{
    public bool $no_injunction = true;
    public bool $impersonal_tone = true;
    public bool $no_syncretism = true;

    public bool $no_diagnosis = true;
    public bool $no_dogma = true;
    public bool $no_prophecy = true;
    public bool $use_possibility_language = true;
}
