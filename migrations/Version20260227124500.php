<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Etend les types AppCard (article/cycle) avec audio/meditation/information.
 * Pourquoi: aligner le schema avec les nouveaux types applicatifs.
 * Info: migration MySQL uniquement (ENUM).
 */
final class Version20260227124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Etend l\'ENUM app_card.type avec audio/meditation/information.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
            MODIFY type ENUM('article','cycle','audio','meditation','information') NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
            MODIFY type ENUM('article','cycle') NOT NULL"
        );
    }
}
