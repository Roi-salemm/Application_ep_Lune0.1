<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optimise les precisions DECIMAL de canonique_data sans troncature.
 */
final class Version20260203161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optimize canonique_data DECIMAL precision without truncation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_ra_ast_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_dec_ast_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_ra_app_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_dec_app_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m10_illum_frac DECIMAL(9,6) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lat_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lon_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lat_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_phi_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lat_deg DECIMAL(13,10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_ra_ast_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m1_dec_ast_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_ra_app_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m2_dec_app_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m10_illum_frac DECIMAL(10,6) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lon_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m31_ecl_lat_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lon_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_pab_lat_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m43_phi_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lon_deg DECIMAL(16,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN s31_ecl_lat_deg DECIMAL(16,10) DEFAULT NULL');
    }
}
