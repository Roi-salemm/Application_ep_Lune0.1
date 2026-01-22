<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260121191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Horizons columns to moon_ephemeris_hour';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');

        if (!$table->hasColumn('delta_au')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD delta_au NUMERIC(16, 12) DEFAULT NULL');
        }
        if (!$table->hasColumn('deldot_km_s')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD deldot_km_s NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_elong_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_elong_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_target_obs_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_target_obs_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_trail')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_trail VARCHAR(8) DEFAULT NULL');
        }
        if (!$table->hasColumn('constellation')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD constellation VARCHAR(8) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');

        if ($table->hasColumn('delta_au')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP delta_au');
        }
        if ($table->hasColumn('deldot_km_s')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP deldot_km_s');
        }
        if ($table->hasColumn('sun_elong_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_elong_deg');
        }
        if ($table->hasColumn('sun_target_obs_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_target_obs_deg');
        }
        if ($table->hasColumn('sun_trail')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_trail');
        }
        if ($table->hasColumn('constellation')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP constellation');
        }
    }
}
