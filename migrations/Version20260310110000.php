<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute un index composite pour les requetes Orb Window filtrees par famille/methode/date evenement.
 */
final class Version20260310110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index on orb_window(window_family, calculation_method, event_at_utc).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('orb_window')) {
            return;
        }

        $table = $schema->getTable('orb_window');
        if (!$table->hasIndex('idx_orb_window_family_method_event')) {
            $this->addSql('CREATE INDEX idx_orb_window_family_method_event ON orb_window (window_family, calculation_method, event_at_utc)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('orb_window')) {
            return;
        }

        $table = $schema->getTable('orb_window');
        if ($table->hasIndex('idx_orb_window_family_method_event')) {
            $this->addSql('DROP INDEX idx_orb_window_family_method_event ON orb_window');
        }
    }
}
