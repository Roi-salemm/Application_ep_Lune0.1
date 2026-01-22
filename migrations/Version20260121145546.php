<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121145546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE moon_ephemeris_hour (id INT AUTO_INCREMENT NOT NULL, ts_utc DATETIME DEFAULT NULL, phase_deg NUMERIC(12, 6) DEFAULT NULL, age_days NUMERIC(12, 6) DEFAULT NULL, diam_km NUMERIC(12, 6) DEFAULT NULL, dist_km NUMERIC(12, 6) DEFAULT NULL, ra_hours NUMERIC(12, 6) DEFAULT NULL, dec_deg NUMERIC(12, 6) DEFAULT NULL, slon_deg NUMERIC(12, 6) DEFAULT NULL, slat_deg NUMERIC(12, 6) DEFAULT NULL, elon_deg NUMERIC(12, 6) DEFAULT NULL, elat_deg NUMERIC(12, 6) DEFAULT NULL, axis_a_deg NUMERIC(12, 6) DEFAULT NULL, raw_line LONGTEXT DEFAULT NULL, created_at_utc DATETIME NOT NULL, run_id_id INT NOT NULL, INDEX IDX_F8E0179743DB84D9 (run_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE moon_nasa_import (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(64) DEFAULT NULL, target VARCHAR(64) DEFAULT NULL, center VARCHAR(64) DEFAULT NULL, year SMALLINT DEFAULT NULL, start_utc DATETIME DEFAULT NULL, stop_utc DATETIME DEFAULT NULL, step_size VARCHAR(64) DEFAULT NULL, time_zone LONGTEXT NOT NULL, sha256 VARCHAR(64) DEFAULT NULL, retrieved_at_utc DATETIME DEFAULT NULL, status VARCHAR(64) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE moon_ephemeris_hour ADD CONSTRAINT FK_F8E0179743DB84D9 FOREIGN KEY (run_id_id) REFERENCES moon_nasa_import (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE moon_ephemeris_hour DROP FOREIGN KEY FK_F8E0179743DB84D9');
        $this->addSql('DROP TABLE moon_ephemeris_hour');
        $this->addSql('DROP TABLE moon_nasa_import');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
