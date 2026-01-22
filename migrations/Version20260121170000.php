<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260121170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add raw_response to moon_nasa_import';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_nasa_import ADD raw_response LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_nasa_import DROP raw_response');
    }
}
