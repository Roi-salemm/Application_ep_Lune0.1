<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajuste app_media pour stocker original/app + ajoute item_type=text pour modules de cycle.
 * Pourquoi: gerer l upload brut + version WebP et l edition de contenu autonome dans les cycles.
 * Info: migration MySQL uniquement (ALTER TABLE + ENUM).
 */
final class Version20260225123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Met a jour app_media (original/app) et ajoute item_type=text.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_media
                ADD original_path VARCHAR(255) NOT NULL,
                ADD app_path VARCHAR(255) NOT NULL,
                ADD original_mime VARCHAR(120) NOT NULL,
                ADD app_mime VARCHAR(120) NOT NULL,
                ADD original_size INT UNSIGNED NOT NULL,
                ADD app_size INT UNSIGNED NOT NULL,
                ADD app_width INT UNSIGNED DEFAULT NULL,
                ADD app_height INT UNSIGNED DEFAULT NULL,
                DROP storage_path,
                DROP filename,
                DROP mime_type,
                DROP file_size,
                DROP width,
                DROP height,
                DROP duration_seconds,
                DROP alt_text,
                DROP caption,
                DROP hash"
        );

        $this->addSql(
            "ALTER TABLE app_cycle_module_item
                MODIFY item_type ENUM('article','audio','video','resource','tool','text') NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_media
                ADD storage_path VARCHAR(255) NOT NULL,
                ADD filename VARCHAR(255) NOT NULL,
                ADD mime_type VARCHAR(120) NOT NULL,
                ADD file_size INT UNSIGNED NOT NULL,
                ADD width INT UNSIGNED DEFAULT NULL,
                ADD height INT UNSIGNED DEFAULT NULL,
                ADD duration_seconds INT UNSIGNED DEFAULT NULL,
                ADD alt_text VARCHAR(255) DEFAULT NULL,
                ADD caption VARCHAR(255) DEFAULT NULL,
                ADD hash VARCHAR(64) DEFAULT NULL,
                DROP original_path,
                DROP app_path,
                DROP original_mime,
                DROP app_mime,
                DROP original_size,
                DROP app_size,
                DROP app_width,
                DROP app_height"
        );

        $this->addSql(
            "ALTER TABLE app_cycle_module_item
                MODIFY item_type ENUM('article','audio','video','resource','tool') NOT NULL"
        );
    }
}
