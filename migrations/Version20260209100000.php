<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bascule la cle etrangere de moon_ephemeris_hour vers import_horizon.
 * Pourquoi: aligner les relations Doctrine sur la table import_horizon.
 */
final class Version20260209100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch moon_ephemeris_hour FK from moon_nasa_import to import_horizon.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');
        if ($table->hasForeignKey('FK_F8E0179743DB84D9')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP FOREIGN KEY FK_F8E0179743DB84D9');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');
        if ($table->hasForeignKey('FK_F8E0179743DB84D9')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP FOREIGN KEY FK_F8E0179743DB84D9');
        }

        $this->addSql('ALTER TABLE moon_ephemeris_hour ADD CONSTRAINT FK_F8E0179743DB84D9 FOREIGN KEY (run_id_id) REFERENCES import_horizon (id)');
    }
}
