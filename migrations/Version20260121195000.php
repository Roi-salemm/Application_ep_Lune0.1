<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260121195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase precision for delta_au and deldot_km_s';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour MODIFY delta_au NUMERIC(18, 14) DEFAULT NULL, MODIFY deldot_km_s NUMERIC(14, 8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour MODIFY delta_au NUMERIC(16, 12) DEFAULT NULL, MODIFY deldot_km_s NUMERIC(12, 6) DEFAULT NULL');
    }
}
