<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260122121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique indexes to prevent duplicate ephemeris rows and phase events';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('moon_ephemeris_hour')) {
            $table = $schema->getTable('moon_ephemeris_hour');
            if (!$table->hasIndex('uniq_moon_ephemeris_hour_ts')) {
                $this->addSql('CREATE UNIQUE INDEX uniq_moon_ephemeris_hour_ts ON moon_ephemeris_hour (ts_utc)');
            }
        }

        if ($schema->hasTable('solar_ephemeris_hour')) {
            $table = $schema->getTable('solar_ephemeris_hour');
            if (!$table->hasIndex('uniq_solar_ephemeris_hour_ts')) {
                $this->addSql('CREATE UNIQUE INDEX uniq_solar_ephemeris_hour_ts ON solar_ephemeris_hour (ts_utc)');
            }
        }

        if ($schema->hasTable('moon_phase_event')) {
            $table = $schema->getTable('moon_phase_event');
            if (!$table->hasIndex('uniq_moon_phase_event_type_ts')) {
                $this->addSql('CREATE UNIQUE INDEX uniq_moon_phase_event_type_ts ON moon_phase_event (event_type, ts_utc)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('moon_ephemeris_hour')) {
            $table = $schema->getTable('moon_ephemeris_hour');
            if ($table->hasIndex('uniq_moon_ephemeris_hour_ts')) {
                $this->addSql('DROP INDEX uniq_moon_ephemeris_hour_ts ON moon_ephemeris_hour');
            }
        }

        if ($schema->hasTable('solar_ephemeris_hour')) {
            $table = $schema->getTable('solar_ephemeris_hour');
            if ($table->hasIndex('uniq_solar_ephemeris_hour_ts')) {
                $this->addSql('DROP INDEX uniq_solar_ephemeris_hour_ts ON solar_ephemeris_hour');
            }
        }

        if ($schema->hasTable('moon_phase_event')) {
            $table = $schema->getTable('moon_phase_event');
            if ($table->hasIndex('uniq_moon_phase_event_type_ts')) {
                $this->addSql('DROP INDEX uniq_moon_phase_event_type_ts ON moon_phase_event');
            }
        }
    }
}
