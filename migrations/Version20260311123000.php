<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree la table sw_text_variant pour les variantes editoriales Symbolic Weather.
 */
final class Version20260311123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sw_text_variant table with self FK source_variant_id and index on (is_validated, is_used).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('sw_text_variant')) {
            return;
        }

        $this->addSql(
            "CREATE TABLE sw_text_variant (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                family VARCHAR(50) NOT NULL,
                reading_mode VARCHAR(50) NOT NULL,
                lang VARCHAR(10) NOT NULL,
                phase_key SMALLINT NOT NULL,
                variant_no INT UNSIGNED NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                card_text LONGTEXT NOT NULL,
                full_text LONGTEXT DEFAULT NULL,
                text_version INT UNSIGNED NOT NULL,
                is_validated TINYINT(1) NOT NULL DEFAULT 0,
                is_used TINYINT(1) NOT NULL DEFAULT 0,
                comment VARCHAR(255) DEFAULT NULL,
                editorial_notes LONGTEXT DEFAULT NULL,
                source_variant_id BIGINT UNSIGNED DEFAULT NULL,
                created_at_utc DATETIME NOT NULL,
                updated_at_utc DATETIME NOT NULL,
                INDEX idx_sw_text_variant_validated_used (is_validated, is_used),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );

        $this->addSql(
            'ALTER TABLE sw_text_variant
                ADD CONSTRAINT FK_SW_TEXT_VARIANT_SOURCE
                FOREIGN KEY (source_variant_id) REFERENCES sw_text_variant (id)
                ON DELETE SET NULL'
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('sw_text_variant')) {
            return;
        }

        $table = $schema->getTable('sw_text_variant');
        if ($table->hasForeignKey('FK_SW_TEXT_VARIANT_SOURCE')) {
            $this->addSql('ALTER TABLE sw_text_variant DROP FOREIGN KEY FK_SW_TEXT_VARIANT_SOURCE');
        }

        $this->addSql('DROP TABLE sw_text_variant');
    }
}

