<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajuste les colonnes m20 pour accepter la precision des valeurs AU brutes.
 * Pourquoi: stocker les valeurs Horizons sans conversion ni troncature.
 */
final class Version20260209120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resize m20_range_km and m20_range_rate_km_s to DECIMAL(22,16).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(22,16) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(22,16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(13,6) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(10,6) DEFAULT NULL');
    }
}
