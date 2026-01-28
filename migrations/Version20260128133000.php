<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260128133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add illumination percentage to moon_ephemeris_hour';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');
        if (!$table->hasColumn('illum_pct')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD illum_pct NUMERIC(5, 2) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');
        if ($table->hasColumn('illum_pct')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP illum_pct');
        }
    }
}
