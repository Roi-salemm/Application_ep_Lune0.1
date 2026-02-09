<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit m20 range/range-rate de AU vers KM et ajuste les precisions.
 * Pourquoi: stocker en kilometres dans canonique_data avec une marge raisonnable.
 */
final class Version20260204125000 extends AbstractMigration
{
    private const AU_TO_KM = 149597870.7;

    public function getDescription(): string
    {
        return 'Convert m20 values to KM and resize DECIMAL columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            'UPDATE canonique_data
             SET m20_range_km = m20_range_km * %.7f
             WHERE m20_range_km IS NOT NULL AND m20_range_km < 10',
            self::AU_TO_KM
        ));
        $this->addSql(sprintf(
            'UPDATE canonique_data
             SET m20_range_rate_km_s = m20_range_rate_km_s * %.7f
             WHERE m20_range_rate_km_s IS NOT NULL AND m20_range_rate_km_s < 10',
            self::AU_TO_KM
        ));
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(13,6) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(10,6) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_km DECIMAL(18,10) DEFAULT NULL');
        $this->addSql('ALTER TABLE canonique_data MODIFY COLUMN m20_range_rate_km_s DECIMAL(18,14) DEFAULT NULL');
    }
}
