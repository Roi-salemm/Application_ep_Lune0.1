<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree les tables sw_display, sw_content et sw_schedule.
 * Pourquoi: structurer la definition metier, la version editoriale et la diffusion temporelle UTC.
 * Info: migration MySQL avec relations Doctrine via colonnes display_id/content_id.
 */
final class Version20260309091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree sw_display, sw_content et sw_schedule avec relations, JSON et horodatage UTC.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE sw_display (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                code VARCHAR(150) NOT NULL,
                family VARCHAR(50) NOT NULL,
                reading_mode VARCHAR(50) NOT NULL,
                phase_key VARCHAR(50) DEFAULT NULL,
                influence_key VARCHAR(50) DEFAULT NULL,
                lang VARCHAR(10) NOT NULL,
                comment VARCHAR(255) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                UNIQUE INDEX uq_sw_display_code (code),
                INDEX idx_sw_display_family_mode (family, reading_mode),
                INDEX idx_sw_display_active (is_active),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );

        $this->addSql(
            "CREATE TABLE sw_content (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                display_id BIGINT UNSIGNED NOT NULL,
                version_no INT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                is_validated TINYINT(1) NOT NULL DEFAULT 0,
                content_json JSON NOT NULL,
                schema_version VARCHAR(20) NOT NULL,
                comment VARCHAR(255) DEFAULT NULL,
                editorial_notes LONGTEXT DEFAULT NULL,
                ai_model VARCHAR(120) DEFAULT NULL,
                ai_prompt_version VARCHAR(80) DEFAULT NULL,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                validated_at_utc DATETIME DEFAULT NULL,
                INDEX idx_sw_content_status (status),
                INDEX idx_sw_content_current (is_current),
                INDEX idx_sw_content_validated (is_validated, validated_at_utc),
                UNIQUE INDEX uq_sw_content_display_version (display_id, version_no),
                PRIMARY KEY(id),
                CONSTRAINT fk_sw_content_display FOREIGN KEY (display_id) REFERENCES sw_display (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );

        $this->addSql(
            "CREATE TABLE sw_schedule (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                display_id BIGINT UNSIGNED NOT NULL,
                content_id BIGINT UNSIGNED NOT NULL,
                schedule_type VARCHAR(30) NOT NULL,
                starts_at_utc DATETIME NOT NULL,
                ends_at_utc DATETIME NOT NULL,
                priority SMALLINT NOT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                comment VARCHAR(255) DEFAULT NULL,
                payload_json JSON DEFAULT NULL,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                INDEX idx_sw_schedule_window (starts_at_utc, ends_at_utc),
                INDEX idx_sw_schedule_display_published (display_id, is_published),
                INDEX idx_sw_schedule_content (content_id),
                INDEX idx_sw_schedule_priority (priority),
                PRIMARY KEY(id),
                CONSTRAINT fk_sw_schedule_display FOREIGN KEY (display_id) REFERENCES sw_display (id) ON DELETE CASCADE,
                CONSTRAINT fk_sw_schedule_content FOREIGN KEY (content_id) REFERENCES sw_content (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sw_schedule');
        $this->addSql('DROP TABLE sw_content');
        $this->addSql('DROP TABLE sw_display');
    }
}
