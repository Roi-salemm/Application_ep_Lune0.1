<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260128123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop solar_ephemeris_hour table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('solar_ephemeris_hour')) {
            $this->addSql('DROP TABLE solar_ephemeris_hour');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('solar_ephemeris_hour')) {
            return;
        }

        $this->addSql('CREATE TABLE solar_ephemeris_hour (id INT AUTO_INCREMENT NOT NULL, ts_utc DATETIME DEFAULT NULL, ra_hours NUMERIC(12, 6) DEFAULT NULL, dec_deg NUMERIC(12, 6) DEFAULT NULL, elon_deg NUMERIC(12, 6) DEFAULT NULL, elat_deg NUMERIC(12, 6) DEFAULT NULL, dist_au NUMERIC(18, 14) DEFAULT NULL, created_at_utc DATETIME NOT NULL, UNIQUE INDEX uniq_solar_ephemeris_hour_ts (ts_utc), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
