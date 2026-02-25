<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les tables de contenu (cards, articles, cycles, medias) pour le maker d article/cycle.
 * Pourquoi: structurer le catalogue, le contenu Tiptap et la hierarchie cycle/module/item.
 * Info: migration MySQL uniquement avec ENUM et index de performance.
 */
final class Version20260225120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree app_card, app_article_content, app_cycle_module, app_cycle_module_item, app_media.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE app_media (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                type ENUM('image','audio','video','document') NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                width INT UNSIGNED DEFAULT NULL,
                height INT UNSIGNED DEFAULT NULL,
                duration_seconds INT UNSIGNED DEFAULT NULL,
                alt_text VARCHAR(255) DEFAULT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                hash VARCHAR(64) DEFAULT NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            )"
        );

        $this->addSql(
            "CREATE TABLE app_card (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                type ENUM('article','cycle') NOT NULL,
                slug VARCHAR(190) NOT NULL,
                title VARCHAR(255) NOT NULL,
                baseline VARCHAR(255) DEFAULT NULL,
                cover_media_id BIGINT UNSIGNED DEFAULT NULL,
                access_level ENUM('free','premium') NOT NULL DEFAULT 'free',
                status ENUM('draft','published') NOT NULL DEFAULT 'draft',
                published_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                UNIQUE INDEX uq_app_card_slug (slug),
                INDEX idx_app_card_status_published (status, published_at),
                INDEX idx_app_card_type (type),
                INDEX idx_app_card_access_level (access_level),
                CONSTRAINT fk_app_card_cover_media FOREIGN KEY (cover_media_id) REFERENCES app_media (id) ON DELETE SET NULL
            )"
        );

        $this->addSql(
            "CREATE TABLE app_article_content (
                card_id BIGINT UNSIGNED NOT NULL,
                body_json JSON NOT NULL,
                reading_minutes SMALLINT UNSIGNED DEFAULT NULL,
                hero_media_id BIGINT UNSIGNED DEFAULT NULL,
                PRIMARY KEY(card_id),
                CONSTRAINT fk_app_article_content_card FOREIGN KEY (card_id) REFERENCES app_card (id) ON DELETE CASCADE,
                CONSTRAINT fk_app_article_content_hero_media FOREIGN KEY (hero_media_id) REFERENCES app_media (id) ON DELETE SET NULL
            )"
        );

        $this->addSql(
            "CREATE TABLE app_cycle_module (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                cycle_card_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                baseline VARCHAR(255) DEFAULT NULL,
                order_index INT NOT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_cycle_module_order (cycle_card_id, order_index),
                UNIQUE INDEX uq_cycle_module_order (cycle_card_id, order_index),
                CONSTRAINT fk_app_cycle_module_card FOREIGN KEY (cycle_card_id) REFERENCES app_card (id) ON DELETE CASCADE
            )"
        );

        $this->addSql(
            "CREATE TABLE app_cycle_module_item (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                module_id BIGINT UNSIGNED NOT NULL,
                item_type ENUM('article','audio','video','resource','tool') NOT NULL,
                order_index INT NOT NULL,
                title_override VARCHAR(255) DEFAULT NULL,
                is_free_preview TINYINT(1) NOT NULL DEFAULT 0,
                ref_card_id BIGINT UNSIGNED DEFAULT NULL,
                ref_media_id BIGINT UNSIGNED DEFAULT NULL,
                external_url VARCHAR(255) DEFAULT NULL,
                content_json JSON DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_cycle_module_item_order (module_id, order_index),
                INDEX idx_cycle_module_item_preview (module_id, is_free_preview, order_index),
                INDEX idx_cycle_module_item_ref_card (ref_card_id),
                INDEX idx_cycle_module_item_ref_media (ref_media_id),
                UNIQUE INDEX uq_cycle_module_item_order (module_id, order_index),
                CONSTRAINT fk_cycle_module_item_module FOREIGN KEY (module_id) REFERENCES app_cycle_module (id) ON DELETE CASCADE,
                CONSTRAINT fk_cycle_module_item_ref_card FOREIGN KEY (ref_card_id) REFERENCES app_card (id) ON DELETE SET NULL,
                CONSTRAINT fk_cycle_module_item_ref_media FOREIGN KEY (ref_media_id) REFERENCES app_media (id) ON DELETE SET NULL
            )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_cycle_module_item');
        $this->addSql('DROP TABLE app_cycle_module');
        $this->addSql('DROP TABLE app_article_content');
        $this->addSql('DROP TABLE app_card');
        $this->addSql('DROP TABLE app_media');
    }
}
