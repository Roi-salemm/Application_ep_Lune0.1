<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Simplifie la taxonomie de sw_display.
 * Pourquoi: basculer sur family (univers) + reading_mode (type de contenu), et retirer les colonnes redondantes.
 * Info: migration MySQL avec normalisation defensive des valeurs existantes.
 */
final class Version20260309153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refond sw_display: family/reading_mode nouveaux ENUM, suppression phase_key/influence_key.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE sw_display
             MODIFY family VARCHAR(50) NOT NULL,
             MODIFY reading_mode VARCHAR(50) NOT NULL"
        );

        $this->addSql(
            "UPDATE sw_display
             SET family = 'symbolic'
             WHERE family IN ('phase_main','influence_trend','influence_event')"
        );
        $this->addSql(
            "UPDATE sw_display
             SET family = 'symbolic'
             WHERE family IS NULL
                OR family NOT IN ('symbolic','western_astro','jyotish','yijing')"
        );

        $this->addSql(
            "UPDATE sw_display
             SET reading_mode = CASE
                WHEN reading_mode IN ('weather','influence','astronomical_event','lunation_name','void_of_course') THEN reading_mode
                WHEN code = 'influence_stellar' THEN 'astronomical_event'
                WHEN code = 'influence_synodic' THEN 'influence'
                WHEN code = 'appellation' THEN 'lunation_name'
                WHEN code = 'symbolic_text' THEN 'weather'
                ELSE 'influence'
             END"
        );

        $this->addSql(
            "ALTER TABLE sw_display
             DROP COLUMN phase_key,
             DROP COLUMN influence_key,
             MODIFY family ENUM('symbolic','western_astro','jyotish','yijing') NOT NULL,
             MODIFY reading_mode ENUM('weather','influence','astronomical_event','lunation_name','void_of_course') NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE sw_display
             ADD phase_key VARCHAR(50) DEFAULT NULL,
             ADD influence_key VARCHAR(50) DEFAULT NULL"
        );

        $this->addSql(
            "UPDATE sw_display
             SET family = 'phase_main'
             WHERE family IS NULL
                OR family NOT IN ('phase_main','influence_trend','influence_event')"
        );
        $this->addSql(
            "UPDATE sw_display
             SET reading_mode = 'symbolic'
             WHERE reading_mode IS NULL
                OR reading_mode NOT IN ('symbolic','western_astro','jyotish','yijing')"
        );

        $this->addSql(
            "ALTER TABLE sw_display
             MODIFY family ENUM('phase_main','influence_trend','influence_event') NOT NULL,
             MODIFY reading_mode ENUM('symbolic','western_astro','jyotish','yijing') NOT NULL"
        );
    }
}
