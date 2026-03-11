<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Transport de saisie pour la creation d un element Symbolic Text.
 * Pourquoi: centraliser les champs du modal create en PHP pour valider cote serveur.
 * Infos: les dates restent en texte UTC (Y-m-d H:i[:s]) puis sont converties dans le controleur.
 */
final class SymbolicTextCreateInput
{
    #[Assert\NotBlank]
    public string $row_code = 'influence_synodic';

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    public string $display_lang = 'fr';

    public bool $display_is_active = true;

    #[Assert\Length(max: 255)]
    public ?string $display_comment = null;

    #[Assert\Length(max: 255)]
    public ?string $label = null;

    #[Assert\Length(max: 255)]
    public ?string $subtitle = null;

    #[Assert\Length(max: 20)]
    public ?string $color = '#315A7B';

    #[Assert\Length(max: 120)]
    public ?string $icon = null;

    public bool $content_is_current = false;

    public bool $content_is_validated = false;

    public bool $schedule_is_published = false;

    #[Assert\Length(max: 20)]
    public ?string $schema_version = '1.0';

    #[Assert\Length(max: 30)]
    public ?string $status = 'draft';

    public ?string $editorial_notes = null;

    public ?string $content_json = null;

    #[Assert\NotBlank]
    public string $starts_at_utc = '';

    #[Assert\NotBlank]
    public string $ends_at_utc = '';

    #[Assert\Range(min: -32768, max: 32767)]
    public int $priority = 100;

    public ?string $schedule_comment = null;

    public ?string $payload_json = null;
}

