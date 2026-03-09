<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit les colonnes metier string en ENUM sur sw_display/sw_content/sw_schedule.
 * Pourquoi: verrouiller les valeurs de reference pour eviter les melanges entre modes et familles.
 * Info: migration MySQL uniquement avec normalisation defensive des valeurs existantes.
 */
final class Version20260309110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertit family/reading_mode/status/schedule_type en ENUM metier.';
    }

    public function up(Schema $schema): void
    {
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
            "UPDATE sw_content
             SET status = 'draft'
             WHERE status IS NULL
                OR status NOT IN ('draft','review','validated','archived')"
        );
        $this->addSql(
            "UPDATE sw_schedule
             SET schedule_type = 'influence_window'
             WHERE schedule_type IS NULL
                OR schedule_type NOT IN ('phase_window','influence_window')"
        );

        $this->addSql(
            "ALTER TABLE sw_display
             MODIFY family ENUM('phase_main','influence_trend','influence_event') NOT NULL,
             MODIFY reading_mode ENUM('symbolic','western_astro','jyotish','yijing') NOT NULL"
        );
        $this->addSql(
            "ALTER TABLE sw_content
             MODIFY status ENUM('draft','review','validated','archived') NOT NULL"
        );
        $this->addSql(
            "ALTER TABLE sw_schedule
             MODIFY schedule_type ENUM('phase_window','influence_window') NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE sw_display
             MODIFY family VARCHAR(50) NOT NULL,
             MODIFY reading_mode VARCHAR(50) NOT NULL"
        );
        $this->addSql(
            "ALTER TABLE sw_content
             MODIFY status VARCHAR(30) NOT NULL"
        );
        $this->addSql(
            "ALTER TABLE sw_schedule
             MODIFY schedule_type VARCHAR(30) NOT NULL"
        );
    }
}
