<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute m29_constellation a canonique_data.
 */
final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add m29_constellation to canonique_data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data ADD m29_constellation VARCHAR(8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data DROP m29_constellation');
    }
}
