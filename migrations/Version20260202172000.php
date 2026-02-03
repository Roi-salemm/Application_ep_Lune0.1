<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260202172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add raw_data JSON column to moon_ephemeris_hour.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour ADD raw_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour DROP raw_data');
    }
}
