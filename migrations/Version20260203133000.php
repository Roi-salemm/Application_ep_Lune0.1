<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree les tables import_horizon et canonique_data.
 * Pourquoi: stocker les reponses brutes Horizons et une base canonique sans calcul.
 * Infos: les colonnes de valeurs seront ajoutees dynamiquement au parse si besoin.
 */
final class Version20260203133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create import_horizon and canonique_data tables (base schema).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_horizon (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(64) DEFAULT NULL, target VARCHAR(64) DEFAULT NULL, center VARCHAR(64) DEFAULT NULL, year SMALLINT DEFAULT NULL, start_utc DATETIME DEFAULT NULL, stop_utc DATETIME DEFAULT NULL, step_size VARCHAR(64) DEFAULT NULL, time_zone LONGTEXT NOT NULL, sha256 VARCHAR(64) DEFAULT NULL, retrieved_at_utc DATETIME DEFAULT NULL, status VARCHAR(64) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, raw_response LONGTEXT DEFAULT NULL, raw_header LONGTEXT DEFAULT NULL, query_params JSON DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE canonique_data (id INT AUTO_INCREMENT NOT NULL, ts_utc DATETIME DEFAULT NULL, m_raw_line LONGTEXT DEFAULT NULL, s_raw_line LONGTEXT DEFAULT NULL, created_at_utc DATETIME NOT NULL, UNIQUE INDEX uniq_canonique_data_ts (ts_utc), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE canonique_data');
        $this->addSql('DROP TABLE import_horizon');
    }
}
