<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recrée la table canonique_data avec un schema fixe geocentrique.
 * Pourquoi: aligner les colonnes sur les quantites Horizons 1,2,10,20,31,43 + Soleil 31.
 * Infos: supprime l'ancien schema dynamique base sur un id auto-incremente.
 */
final class Version20260203150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rebuild canonique_data with fixed geocentric schema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS canonique_data');
        $this->addSql('CREATE TABLE canonique_data (
            ts_utc DATETIME NOT NULL,
            m1_ra_ast_deg DECIMAL(10,6) DEFAULT NULL,
            m1_dec_ast_deg DECIMAL(10,6) DEFAULT NULL,
            m2_ra_app_deg DECIMAL(10,6) DEFAULT NULL,
            m2_dec_app_deg DECIMAL(10,6) DEFAULT NULL,
            m10_illum_frac DECIMAL(6,3) DEFAULT NULL,
            m20_range_km DECIMAL(12,3) DEFAULT NULL,
            m20_range_rate_km_s DECIMAL(12,6) DEFAULT NULL,
            m31_ecl_lon_deg DECIMAL(10,6) DEFAULT NULL,
            m31_ecl_lat_deg DECIMAL(10,6) DEFAULT NULL,
            m43_pab_lon_deg DECIMAL(10,6) DEFAULT NULL,
            m43_pab_lat_deg DECIMAL(10,6) DEFAULT NULL,
            m43_phi_deg DECIMAL(10,6) DEFAULT NULL,
            s31_ecl_lon_deg DECIMAL(10,6) DEFAULT NULL,
            s31_ecl_lat_deg DECIMAL(10,6) DEFAULT NULL,
            m_raw_line TEXT DEFAULT NULL,
            s_raw_line TEXT DEFAULT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (ts_utc),
            UNIQUE INDEX uniq_canonique_data_ts (ts_utc)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS canonique_data');
        $this->addSql('CREATE TABLE canonique_data (
            id INT AUTO_INCREMENT NOT NULL,
            ts_utc DATETIME DEFAULT NULL,
            m_raw_line LONGTEXT DEFAULT NULL,
            s_raw_line LONGTEXT DEFAULT NULL,
            created_at_utc DATETIME NOT NULL,
            UNIQUE INDEX uniq_canonique_data_ts (ts_utc),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }
}
