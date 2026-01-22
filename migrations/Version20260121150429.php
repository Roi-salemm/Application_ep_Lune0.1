<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121150429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE moon_jyotish (id INT AUTO_INCREMENT NOT NULL, moon_ecl_lon_deg NUMERIC(12, 10) DEFAULT NULL, moon_ecl_lat_deg NUMERIC(15, 10) DEFAULT NULL, sun_ecl_lon_deg NUMERIC(15, 10) DEFAULT NULL, sun_ecl_lat_deg NUMERIC(15, 10) DEFAULT NULL, ayanamsa_deg NUMERIC(15, 10) DEFAULT NULL, moon_sid_lon_deg NUMERIC(15, 10) DEFAULT NULL, sun_sid_lon_deg NUMERIC(15, 10) DEFAULT NULL, tithi_index SMALLINT DEFAULT NULL, nakshatra_index SMALLINT DEFAULT NULL, rashi_index SMALLINT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE moon_jyotish');
    }
}
