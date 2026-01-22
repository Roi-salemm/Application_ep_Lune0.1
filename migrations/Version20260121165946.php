<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121165946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE moon_ephemeris_hour ADD delta_au NUMERIC(16, 12) DEFAULT NULL, ADD deldot_km_s NUMERIC(12, 6) DEFAULT NULL, ADD sun_elong_deg NUMERIC(12, 6) DEFAULT NULL, ADD sun_target_obs_deg NUMERIC(12, 6) DEFAULT NULL, ADD sun_trail VARCHAR(8) DEFAULT NULL, ADD constellation VARCHAR(8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE moon_ephemeris_hour DROP delta_au, DROP deldot_km_s, DROP sun_elong_deg, DROP sun_target_obs_deg, DROP sun_trail, DROP constellation');
    }
}
