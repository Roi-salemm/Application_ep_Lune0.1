<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs mini/tel/tab et rend original_path nullable.
 * Pourquoi: stocker trois tailles d image par appareil sans conserver l original sur disque.
 * Info: migration MySQL uniquement (ALTER TABLE).
 */
final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs d images mini/tel/tab et rend original_path nullable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_media
                MODIFY original_path VARCHAR(255) DEFAULT NULL,
                ADD img_mini_path VARCHAR(255) DEFAULT NULL,
                ADD img_mini_mime VARCHAR(120) DEFAULT NULL,
                ADD img_mini_size INT UNSIGNED DEFAULT NULL,
                ADD img_mini_width INT UNSIGNED DEFAULT NULL,
                ADD img_mini_height INT UNSIGNED DEFAULT NULL,
                ADD img_tel_path VARCHAR(255) DEFAULT NULL,
                ADD img_tel_mime VARCHAR(120) DEFAULT NULL,
                ADD img_tel_size INT UNSIGNED DEFAULT NULL,
                ADD img_tel_width INT UNSIGNED DEFAULT NULL,
                ADD img_tel_height INT UNSIGNED DEFAULT NULL,
                ADD img_tab_path VARCHAR(255) DEFAULT NULL,
                ADD img_tab_mime VARCHAR(120) DEFAULT NULL,
                ADD img_tab_size INT UNSIGNED DEFAULT NULL,
                ADD img_tab_width INT UNSIGNED DEFAULT NULL,
                ADD img_tab_height INT UNSIGNED DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_media
                DROP img_mini_path,
                DROP img_mini_mime,
                DROP img_mini_size,
                DROP img_mini_width,
                DROP img_mini_height,
                DROP img_tel_path,
                DROP img_tel_mime,
                DROP img_tel_size,
                DROP img_tel_width,
                DROP img_tel_height,
                DROP img_tab_path,
                DROP img_tab_mime,
                DROP img_tab_size,
                DROP img_tab_width,
                DROP img_tab_height,
                MODIFY original_path VARCHAR(255) NOT NULL"
        );
    }
}
