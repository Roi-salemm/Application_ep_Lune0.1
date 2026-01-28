<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display fields for moon phase events';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('moon_phase_event')) {
            $table = $schema->getTable('moon_phase_event');
            if (!$table->hasColumn('phase_name')) {
                $this->addSql('ALTER TABLE moon_phase_event ADD phase_name VARCHAR(64) DEFAULT NULL');
            }
            if (!$table->hasColumn('display_at_utc')) {
                $this->addSql('ALTER TABLE moon_phase_event ADD display_at_utc DATETIME DEFAULT NULL');
            }
            if (!$table->hasColumn('illum_pct')) {
                $this->addSql('ALTER TABLE moon_phase_event ADD illum_pct NUMERIC(5, 2) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('moon_phase_event')) {
            $table = $schema->getTable('moon_phase_event');
            if ($table->hasColumn('phase_name')) {
                $this->addSql('ALTER TABLE moon_phase_event DROP phase_name');
            }
            if ($table->hasColumn('display_at_utc')) {
                $this->addSql('ALTER TABLE moon_phase_event DROP display_at_utc');
            }
            if ($table->hasColumn('illum_pct')) {
                $this->addSql('ALTER TABLE moon_phase_event DROP illum_pct');
            }
        }
    }
}
