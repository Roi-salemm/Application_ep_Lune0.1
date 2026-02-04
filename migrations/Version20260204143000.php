<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit canonique_data vers des entiers scales (mdeg/ppm/m/mm_s).
 */
final class Version20260204143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch canonique_data to scaled INT columns (mdeg/ppm/m/mm_s).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data
            ADD COLUMN m1_ra_ast_mdeg INT DEFAULT NULL,
            ADD COLUMN m1_dec_ast_mdeg INT DEFAULT NULL,
            ADD COLUMN m2_ra_app_mdeg INT DEFAULT NULL,
            ADD COLUMN m2_dec_app_mdeg INT DEFAULT NULL,
            ADD COLUMN m10_illum_ppm INT UNSIGNED DEFAULT NULL,
            ADD COLUMN m20_range_m INT UNSIGNED DEFAULT NULL,
            ADD COLUMN m20_range_rate_mm_s INT DEFAULT NULL,
            ADD COLUMN m31_ecl_lon_mdeg INT DEFAULT NULL,
            ADD COLUMN m31_ecl_lat_mdeg INT DEFAULT NULL,
            ADD COLUMN m43_pab_lon_mdeg INT DEFAULT NULL,
            ADD COLUMN m43_pab_lat_mdeg INT DEFAULT NULL,
            ADD COLUMN m43_phi_mdeg INT UNSIGNED DEFAULT NULL,
            ADD COLUMN s31_ecl_lon_mdeg INT DEFAULT NULL,
            ADD COLUMN s31_ecl_lat_mdeg INT DEFAULT NULL
        ');

        $this->addSql('UPDATE canonique_data SET
            m1_ra_ast_mdeg = ROUND(m1_ra_ast_deg * 1000),
            m1_dec_ast_mdeg = ROUND(m1_dec_ast_deg * 1000),
            m2_ra_app_mdeg = ROUND(m2_ra_app_deg * 1000),
            m2_dec_app_mdeg = ROUND(m2_dec_app_deg * 1000),
            m10_illum_ppm = ROUND(m10_illum_frac * 1000000),
            m20_range_m = ROUND(m20_range_km * 1000),
            m20_range_rate_mm_s = ROUND(m20_range_rate_km_s * 1000000),
            m31_ecl_lon_mdeg = ROUND(m31_ecl_lon_deg * 1000),
            m31_ecl_lat_mdeg = ROUND(m31_ecl_lat_deg * 1000),
            m43_pab_lon_mdeg = ROUND(m43_pab_lon_deg * 1000),
            m43_pab_lat_mdeg = ROUND(m43_pab_lat_deg * 1000),
            m43_phi_mdeg = ROUND(m43_phi_deg * 1000),
            s31_ecl_lon_mdeg = ROUND(s31_ecl_lon_deg * 1000),
            s31_ecl_lat_mdeg = ROUND(s31_ecl_lat_deg * 1000)
        ');

        $this->addSql('ALTER TABLE canonique_data
            DROP COLUMN m1_ra_ast_deg,
            DROP COLUMN m1_dec_ast_deg,
            DROP COLUMN m2_ra_app_deg,
            DROP COLUMN m2_dec_app_deg,
            DROP COLUMN m10_illum_frac,
            DROP COLUMN m20_range_km,
            DROP COLUMN m20_range_rate_km_s,
            DROP COLUMN m31_ecl_lon_deg,
            DROP COLUMN m31_ecl_lat_deg,
            DROP COLUMN m43_pab_lon_deg,
            DROP COLUMN m43_pab_lat_deg,
            DROP COLUMN m43_phi_deg,
            DROP COLUMN s31_ecl_lon_deg,
            DROP COLUMN s31_ecl_lat_deg
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data
            ADD COLUMN m1_ra_ast_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m1_dec_ast_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m2_ra_app_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m2_dec_app_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m10_illum_frac DECIMAL(9,6) DEFAULT NULL,
            ADD COLUMN m20_range_km DECIMAL(20,14) DEFAULT NULL,
            ADD COLUMN m20_range_rate_km_s DECIMAL(20,14) DEFAULT NULL,
            ADD COLUMN m31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m31_ecl_lat_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m43_pab_lon_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m43_pab_lat_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN m43_phi_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN s31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL,
            ADD COLUMN s31_ecl_lat_deg DECIMAL(13,10) DEFAULT NULL
        ');

        $this->addSql('UPDATE canonique_data SET
            m1_ra_ast_deg = m1_ra_ast_mdeg / 1000,
            m1_dec_ast_deg = m1_dec_ast_mdeg / 1000,
            m2_ra_app_deg = m2_ra_app_mdeg / 1000,
            m2_dec_app_deg = m2_dec_app_mdeg / 1000,
            m10_illum_frac = m10_illum_ppm / 1000000,
            m20_range_km = m20_range_m / 1000,
            m20_range_rate_km_s = m20_range_rate_mm_s / 1000000,
            m31_ecl_lon_deg = m31_ecl_lon_mdeg / 1000,
            m31_ecl_lat_deg = m31_ecl_lat_mdeg / 1000,
            m43_pab_lon_deg = m43_pab_lon_mdeg / 1000,
            m43_pab_lat_deg = m43_pab_lat_mdeg / 1000,
            m43_phi_deg = m43_phi_mdeg / 1000,
            s31_ecl_lon_deg = s31_ecl_lon_mdeg / 1000,
            s31_ecl_lat_deg = s31_ecl_lat_mdeg / 1000
        ');

        $this->addSql('ALTER TABLE canonique_data
            DROP COLUMN m1_ra_ast_mdeg,
            DROP COLUMN m1_dec_ast_mdeg,
            DROP COLUMN m2_ra_app_mdeg,
            DROP COLUMN m2_dec_app_mdeg,
            DROP COLUMN m10_illum_ppm,
            DROP COLUMN m20_range_m,
            DROP COLUMN m20_range_rate_mm_s,
            DROP COLUMN m31_ecl_lon_mdeg,
            DROP COLUMN m31_ecl_lat_mdeg,
            DROP COLUMN m43_pab_lon_mdeg,
            DROP COLUMN m43_pab_lat_mdeg,
            DROP COLUMN m43_phi_mdeg,
            DROP COLUMN s31_ecl_lon_mdeg,
            DROP COLUMN s31_ecl_lat_mdeg
        ');
    }
}
