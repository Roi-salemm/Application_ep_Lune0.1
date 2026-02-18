<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: ajoute la table month_parse_coverage.
 * Pourquoi: suivre la couverture mensuelle sans scanner canonique_data/ms_mapping.
 * Infos: une ligne par mois et par table cible, avec statut et timestamps.
 */
final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table month_parse_coverage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE month_parse_coverage (
                id INT AUTO_INCREMENT NOT NULL,
                target_table VARCHAR(32) NOT NULL,
                month_key VARCHAR(7) NOT NULL,
                status VARCHAR(16) NOT NULL,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                UNIQUE INDEX uniq_month_parse_coverage_target_month (target_table, month_key),
                INDEX idx_month_parse_coverage_month (month_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE month_parse_coverage');
    }
}
