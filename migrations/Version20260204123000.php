<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne le schema ms_mapping sur l entite Doctrine.
 * Pourquoi: typer le champ phase en SMALLINT cote base.
 */
final class Version20260204123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align ms_mapping schema with ORM (phase as SMALLINT).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ms_mapping MODIFY COLUMN phase SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ms_mapping MODIFY COLUMN phase TINYINT DEFAULT NULL');
    }
}
