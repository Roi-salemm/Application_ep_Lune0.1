<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de colonnes de traçabilité par pipeline (pipeline_id/stage/fingerprint)
 * afin de :
 * - regrouper les étapes (router/safety/generation) sous un même pipeline_id,
 * - filtrer l'UI sur stage=generation,
 * - éviter les doublons (fingerprint).
 */
final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute pipeline_id, stage, fingerprint aux tables ia_admin_log et ia_admin_historique.';
    }

    public function up(Schema $schema): void
    {
        // ia_admin_log
        $this->addSql("ALTER TABLE ia_admin_log ADD pipeline_id VARCHAR(36) DEFAULT NULL");
        $this->addSql("ALTER TABLE ia_admin_log ADD stage VARCHAR(20) NOT NULL DEFAULT 'generation'");
        $this->addSql("ALTER TABLE ia_admin_log ADD fingerprint VARCHAR(64) DEFAULT NULL");
        $this->addSql("CREATE INDEX idx_ia_admin_log_pipeline_stage ON ia_admin_log (pipeline_id, stage)");
        $this->addSql("CREATE INDEX idx_ia_admin_log_fingerprint ON ia_admin_log (fingerprint)");

        // ia_admin_historique
        $this->addSql("ALTER TABLE ia_admin_historique ADD pipeline_id VARCHAR(36) DEFAULT NULL");
        $this->addSql("ALTER TABLE ia_admin_historique ADD stage VARCHAR(20) NOT NULL DEFAULT 'generation'");
        $this->addSql("ALTER TABLE ia_admin_historique ADD fingerprint VARCHAR(64) DEFAULT NULL");
        $this->addSql("CREATE INDEX idx_hist_pipeline_stage ON ia_admin_historique (pipeline_id, stage)");
        $this->addSql("CREATE INDEX idx_hist_fingerprint ON ia_admin_historique (fingerprint)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX idx_hist_fingerprint ON ia_admin_historique");
        $this->addSql("DROP INDEX idx_hist_pipeline_stage ON ia_admin_historique");
        $this->addSql("ALTER TABLE ia_admin_historique DROP fingerprint");
        $this->addSql("ALTER TABLE ia_admin_historique DROP stage");
        $this->addSql("ALTER TABLE ia_admin_historique DROP pipeline_id");

        $this->addSql("DROP INDEX idx_ia_admin_log_fingerprint ON ia_admin_log");
        $this->addSql("DROP INDEX idx_ia_admin_log_pipeline_stage ON ia_admin_log");
        $this->addSql("ALTER TABLE ia_admin_log DROP fingerprint");
        $this->addSql("ALTER TABLE ia_admin_log DROP stage");
        $this->addSql("ALTER TABLE ia_admin_log DROP pipeline_id");
    }
}
