<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260122120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moon_phase_event and solar_ephemeris_hour tables';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('moon_phase_event')) {
            $this->addSql('CREATE TABLE moon_phase_event (id INT AUTO_INCREMENT NOT NULL, ts_utc DATETIME NOT NULL, event_type VARCHAR(32) NOT NULL, phase_deg NUMERIC(8, 4) DEFAULT NULL, precision_sec SMALLINT DEFAULT NULL, source VARCHAR(16) DEFAULT NULL, created_at_utc DATETIME NOT NULL, INDEX IDX_MOON_PHASE_EVENT_TS (ts_utc), INDEX IDX_MOON_PHASE_EVENT_TYPE (event_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('solar_ephemeris_hour')) {
            $this->addSql('CREATE TABLE solar_ephemeris_hour (id INT AUTO_INCREMENT NOT NULL, ts_utc DATETIME DEFAULT NULL, ra_hours NUMERIC(12, 6) DEFAULT NULL, dec_deg NUMERIC(12, 6) DEFAULT NULL, elon_deg NUMERIC(12, 6) DEFAULT NULL, elat_deg NUMERIC(12, 6) DEFAULT NULL, dist_au NUMERIC(18, 14) DEFAULT NULL, created_at_utc DATETIME NOT NULL, INDEX IDX_SOLAR_EPHEMERIS_HOUR_TS (ts_utc), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('moon_phase_event')) {
            $this->addSql('DROP TABLE moon_phase_event');
        }

        if ($schema->hasTable('solar_ephemeris_hour')) {
            $this->addSql('DROP TABLE solar_ephemeris_hour');
        }
    }
}
