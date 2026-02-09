<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire la cle etrangere run_id_id de moon_ephemeris_hour.
 * Pourquoi: moon_ephemeris_hour est obsolette et ne doit plus bloquer import_horizon.
 */
final class Version20260209101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop FK on moon_ephemeris_hour.run_id_id.';
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
            return;
        }

        $this->addSql('ALTER TABLE moon_ephemeris_hour ADD CONSTRAINT FK_F8E0179743DB84D9 FOREIGN KEY (run_id_id) REFERENCES import_horizon (id)');
    }
}
