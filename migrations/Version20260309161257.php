<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cree la table orb_window.
 */
final class Version20260309161257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orb_window with UTC timestamps and business indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orb_window (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, window_family VARCHAR(50) NOT NULL, phase_key VARCHAR(50) NOT NULL, event_at_utc DATETIME NOT NULL, starts_at_utc DATETIME NOT NULL, ends_at_utc DATETIME NOT NULL, lunation_key VARCHAR(50) DEFAULT NULL, sequence_no INT DEFAULT NULL, calculation_method VARCHAR(100) NOT NULL, created_at_utc DATETIME NOT NULL, updated_at_utc DATETIME NOT NULL, INDEX idx_orb_window_family (window_family), INDEX idx_orb_window_phase_key (phase_key), INDEX idx_orb_window_event_at (event_at_utc), INDEX idx_orb_window_calc_method (calculation_method), INDEX idx_orb_window_window (starts_at_utc, ends_at_utc), INDEX idx_orb_window_phase_family_event (phase_key, window_family, event_at_utc), INDEX idx_orb_window_lunation_family_seq (lunation_key, window_family, sequence_no), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE orb_window');
    }
}
