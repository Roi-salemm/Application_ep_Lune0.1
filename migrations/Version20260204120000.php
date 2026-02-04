<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree la table ms_mapping (agregation journaliere).
 */
final class Version20260204120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ms_mapping daily aggregation table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ms_mapping (
            id INT AUTO_INCREMENT NOT NULL,
            ts_utc DATETIME NOT NULL,
            m43_pab_lon_deg DECIMAL(13,10) DEFAULT NULL,
            m10_illum_frac DECIMAL(9,6) DEFAULT NULL,
            m31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL,
            s31_ecl_lon_deg DECIMAL(13,10) DEFAULT NULL,
            phase TINYINT DEFAULT NULL,
            phase_hour DATETIME DEFAULT NULL,
            UNIQUE INDEX uniq_ms_mapping_ts (ts_utc),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ms_mapping');
    }
}
