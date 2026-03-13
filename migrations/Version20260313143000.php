<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree la table sw_snapshot pour la projection front.
 * Pourquoi: exposer une table plate, prete a servir, en liant display/content/schedule.
 * Info: la contrainte unique sw_schedule_id garantit 1 ligne snapshot par texte.
 */
final class Version20260313143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sw_snapshot projection table (one row per sw_schedule).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('sw_snapshot')) {
            return;
        }

        $this->addSql(
            "CREATE TABLE sw_snapshot (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                sw_display_id BIGINT UNSIGNED NOT NULL,
                sw_content_id BIGINT UNSIGNED NOT NULL,
                sw_schedule_id BIGINT UNSIGNED NOT NULL,
                lang VARCHAR(10) NOT NULL,
                family VARCHAR(50) NOT NULL,
                reading_mode VARCHAR(50) NOT NULL,
                card_title VARCHAR(255) DEFAULT NULL,
                card_text LONGTEXT NOT NULL,
                content_json JSON NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uq_sw_snapshot_sw_schedule (sw_schedule_id),
                INDEX idx_sw_snapshot_active (is_active),
                INDEX idx_sw_snapshot_period (starts_at, ends_at),
                INDEX idx_sw_snapshot_family_mode (family, reading_mode, lang),
                INDEX idx_sw_snapshot_sw_display (sw_display_id),
                INDEX idx_sw_snapshot_sw_content (sw_content_id),
                PRIMARY KEY(id),
                CONSTRAINT fk_sw_snapshot_sw_display FOREIGN KEY (sw_display_id) REFERENCES sw_display (id) ON DELETE CASCADE,
                CONSTRAINT fk_sw_snapshot_sw_content FOREIGN KEY (sw_content_id) REFERENCES sw_content (id) ON DELETE CASCADE,
                CONSTRAINT fk_sw_snapshot_sw_schedule FOREIGN KEY (sw_schedule_id) REFERENCES sw_schedule (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('sw_snapshot')) {
            return;
        }

        $this->addSql('DROP TABLE sw_snapshot');
    }
}

