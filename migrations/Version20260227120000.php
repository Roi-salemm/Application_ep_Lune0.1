<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Etend les statuts AppCard pour inclure hidden/blocked/scheduled.
 * Pourquoi: aligner le schema avec les nouveaux statuts applicatifs.
 * Info: migration MySQL uniquement (ENUM).
 */
final class Version20260227120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Etend l'ENUM app_card.status avec hidden/blocked/scheduled.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
            MODIFY status ENUM('draft','published','hidden','blocked','scheduled') NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
            MODIFY status ENUM('draft','published') NOT NULL DEFAULT 'draft'"
        );
    }
}
