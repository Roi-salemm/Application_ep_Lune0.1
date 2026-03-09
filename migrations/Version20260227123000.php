<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute une description aux cards.
 * Pourquoi: stocker un texte long optionnel pour l affichage.
 * Info: migration MySQL uniquement.
 */
final class Version20260227123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne description sur app_card.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_card ADD description TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_card DROP description');
    }
}
