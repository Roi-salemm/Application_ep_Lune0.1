<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recree canonique_data avec DECIMAL haute precision (sans troncature).
 * WARNING: supprime toutes les donnees existantes.
 */
final class Version20260204153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate canonique_data with high-precision DECIMAL columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS canonique_data');
        $this->addSql('CREATE TABLE canonique_data (
            ts_utc DATETIME NOT NULL,
            m1_ra_ast_deg DECIMAL(20,12) DEFAULT NULL,
            m1_dec_ast_deg DECIMAL(20,12) DEFAULT NULL,
            m2_ra_app_deg DECIMAL(20,12) DEFAULT NULL,
            m2_dec_app_deg DECIMAL(20,12) DEFAULT NULL,
            m10_illum_frac DECIMAL(20,14) DEFAULT NULL,
            m20_range_km DECIMAL(24,12) DEFAULT NULL,
            m20_range_rate_km_s DECIMAL(24,14) DEFAULT NULL,
            m31_ecl_lon_deg DECIMAL(20,12) DEFAULT NULL,
            m31_ecl_lat_deg DECIMAL(20,12) DEFAULT NULL,
            m43_pab_lon_deg DECIMAL(20,12) DEFAULT NULL,
            m43_pab_lat_deg DECIMAL(20,12) DEFAULT NULL,
            m43_phi_deg DECIMAL(20,12) DEFAULT NULL,
            s31_ecl_lon_deg DECIMAL(20,12) DEFAULT NULL,
            s31_ecl_lat_deg DECIMAL(20,12) DEFAULT NULL,
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
            ts_utc DATETIME NOT NULL,
            m1_ra_ast_deg DECIMAL(16,10) DEFAULT NULL,
            m1_dec_ast_deg DECIMAL(16,10) DEFAULT NULL,
            m2_ra_app_deg DECIMAL(16,10) DEFAULT NULL,
            m2_dec_app_deg DECIMAL(16,10) DEFAULT NULL,
            m10_illum_frac DECIMAL(10,6) DEFAULT NULL,
            m20_range_km DECIMAL(20,14) DEFAULT NULL,
            m20_range_rate_km_s DECIMAL(20,14) DEFAULT NULL,
            m31_ecl_lon_deg DECIMAL(16,10) DEFAULT NULL,
            m31_ecl_lat_deg DECIMAL(16,10) DEFAULT NULL,
            m43_pab_lon_deg DECIMAL(16,10) DEFAULT NULL,
            m43_pab_lat_deg DECIMAL(16,10) DEFAULT NULL,
            m43_phi_deg DECIMAL(16,10) DEFAULT NULL,
            s31_ecl_lon_deg DECIMAL(16,10) DEFAULT NULL,
            s31_ecl_lat_deg DECIMAL(16,10) DEFAULT NULL,
            m_raw_line TEXT DEFAULT NULL,
            s_raw_line TEXT DEFAULT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (ts_utc),
            UNIQUE INDEX uniq_canonique_data_ts (ts_utc)
        ) DEFAULT CHARACTER SET utf8mb4');
    }
}
