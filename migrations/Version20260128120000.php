<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add solar coordinates and timing/topo columns to moon_ephemeris_hour';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');

        if (!$table->hasColumn('sub_obs_lon_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sub_obs_lon_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sub_obs_lat_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sub_obs_lat_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_ra_hours')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_ra_hours NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_dec_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_dec_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_ecl_lon_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_ecl_lon_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_ecl_lat_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_ecl_lat_deg NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('sun_dist_au')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD sun_dist_au NUMERIC(18, 14) DEFAULT NULL');
        }
        if (!$table->hasColumn('delta_t_sec')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD delta_t_sec NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('dut1_sec')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD dut1_sec NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('pressure_hpa')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD pressure_hpa NUMERIC(12, 6) DEFAULT NULL');
        }
        if (!$table->hasColumn('temperature_c')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour ADD temperature_c NUMERIC(12, 6) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('moon_ephemeris_hour')) {
            return;
        }

        $table = $schema->getTable('moon_ephemeris_hour');

        if ($table->hasColumn('sub_obs_lon_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sub_obs_lon_deg');
        }
        if ($table->hasColumn('sub_obs_lat_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sub_obs_lat_deg');
        }
        if ($table->hasColumn('sun_ra_hours')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_ra_hours');
        }
        if ($table->hasColumn('sun_dec_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_dec_deg');
        }
        if ($table->hasColumn('sun_ecl_lon_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_ecl_lon_deg');
        }
        if ($table->hasColumn('sun_ecl_lat_deg')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_ecl_lat_deg');
        }
        if ($table->hasColumn('sun_dist_au')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP sun_dist_au');
        }
        if ($table->hasColumn('delta_t_sec')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP delta_t_sec');
        }
        if ($table->hasColumn('dut1_sec')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP dut1_sec');
        }
        if ($table->hasColumn('pressure_hpa')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP pressure_hpa');
        }
        if ($table->hasColumn('temperature_c')) {
            $this->addSql('ALTER TABLE moon_ephemeris_hour DROP temperature_c');
        }
    }
}
