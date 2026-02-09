<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Elargit les DECIMAL pour conserver toutes les decimales brutes.
 */
final class Version20260204154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen canonique_data DECIMAL scales to avoid truncation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_ra_ast_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_dec_ast_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_ra_app_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_dec_app_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m10_illum_frac DECIMAL(30,20) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lon_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lat_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lon_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lat_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_phi_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lon_deg DECIMAL(30,18) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lat_deg DECIMAL(30,18) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_ra_ast_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_dec_ast_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_ra_app_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_dec_app_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m10_illum_frac DECIMAL(20,14) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(24,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(24,14) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lon_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lat_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lon_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lat_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_phi_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lon_deg DECIMAL(20,12) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lat_deg DECIMAL(20,12) DEFAULT NULL');
    }
}
