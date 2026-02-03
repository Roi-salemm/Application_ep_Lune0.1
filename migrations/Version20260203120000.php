<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme les colonnes non ambigues de moon_ephemeris_hour avec un prefixe mXX_/sXX_.
 * Pourquoi: afficher immediatement la correspondance entre champs et quantites Horizons.
 * Infos: les colonnes ambigues ou calculees ne sont pas renommees dans cette migration.
 */
final class Version20260203120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prefixe mXX_/sXX_ sur colonnes Horizons non ambigues (moon_ephemeris_hour).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN illum_pct TO m10_illum_pct');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN dist_km TO m20_dist_km');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN delta_au TO m20_delta_au');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN deldot_km_s TO m20_deldot_km_s');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_elong_deg TO m23_sun_elong_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_trail TO m23_sun_trail');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_target_obs_deg TO m24_sun_target_obs_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN constellation TO m29_constellation');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN delta_t_sec TO m30_delta_t_sec');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN dut1_sec TO m49_dut1_sec');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN elon_deg TO m31_elon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN elat_deg TO m31_elat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sub_obs_lon_deg TO m14_sub_obs_lon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sub_obs_lat_deg TO m14_sub_obs_lat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN slon_deg TO m15_slon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN slat_deg TO m15_slat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_ra_hours TO s1_sun_ra_hours');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_dec_deg TO s1_sun_dec_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_ecl_lon_deg TO s31_sun_ecl_lon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_ecl_lat_deg TO s31_sun_ecl_lat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN sun_dist_au TO s20_sun_dist_au');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m10_illum_pct TO illum_pct');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m20_dist_km TO dist_km');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m20_delta_au TO delta_au');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m20_deldot_km_s TO deldot_km_s');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m23_sun_elong_deg TO sun_elong_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m23_sun_trail TO sun_trail');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m24_sun_target_obs_deg TO sun_target_obs_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m29_constellation TO constellation');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m30_delta_t_sec TO delta_t_sec');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m49_dut1_sec TO dut1_sec');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m31_elon_deg TO elon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m31_elat_deg TO elat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m14_sub_obs_lon_deg TO sub_obs_lon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m14_sub_obs_lat_deg TO sub_obs_lat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m15_slon_deg TO slon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN m15_slat_deg TO slat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN s1_sun_ra_hours TO sun_ra_hours');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN s1_sun_dec_deg TO sun_dec_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN s31_sun_ecl_lon_deg TO sun_ecl_lon_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN s31_sun_ecl_lat_deg TO sun_ecl_lat_deg');
        $this->addSql('ALTER TABLE moon_ephemeris_hour RENAME COLUMN s20_sun_dist_au TO sun_dist_au');
    }
}
