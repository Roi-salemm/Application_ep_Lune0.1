<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ featured_rank pour la mise en avant des cards.
 * Pourquoi: permettre un tri manuel simple pour l'app mobile.
 * Info: migration MySQL uniquement (ALTER TABLE + INDEX).
 */
final class Version20260226140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute featured_rank sur app_card pour gerer la mise en avant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
                ADD featured_rank INT UNSIGNED DEFAULT NULL,
                ADD INDEX idx_app_card_featured_rank (featured_rank)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE app_card
                DROP INDEX idx_app_card_featured_rank,
                DROP featured_rank"
        );
    }
}
