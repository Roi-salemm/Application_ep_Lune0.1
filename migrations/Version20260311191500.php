<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme la taxonomie family/reading_mode des flux SW.
 * Pourquoi: adopter des prefixes explicites par famille (SYM_*, AST_*) et remplacer western_astro par astrologie.
 * Info: migration defensive qui convertit d'abord en VARCHAR puis reapplique les ENUM metier.
 */
final class Version20260311191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename sw_display family/reading_mode to astrologie + prefixed modes, and align sw_text_variant weather mode.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('sw_display')) {
            $this->addSql(
                "ALTER TABLE sw_display
                 MODIFY family VARCHAR(50) NOT NULL,
                 MODIFY reading_mode VARCHAR(50) NOT NULL"
            );

            $this->addSql(
                "UPDATE sw_display
                 SET family = CASE
                    WHEN LOWER(TRIM(family)) IN ('astrologie','western_astro','western astro') THEN 'astrologie'
                    WHEN LOWER(TRIM(family)) = 'jyotish' THEN 'jyotish'
                    WHEN LOWER(TRIM(family)) = 'yijing' THEN 'yijing'
                    ELSE 'symbolic'
                 END"
            );

            $this->addSql(
                "UPDATE sw_display
                 SET reading_mode = CASE
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_weather','weather') THEN 'SYM_Weather'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_influence','influence') THEN 'SYM_Influence'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_astronomicalevent','astronomical_event','astronomical event') THEN 'SYM_AstronomicalEvent'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_lunationname','lunation_name','lunationname') THEN 'SYM_LunationName'
                    WHEN LOWER(TRIM(reading_mode)) IN ('ast_void_of_cours','void_of_course','void of course') THEN 'AST_Void_of_cours'
                    WHEN family = 'astrologie' THEN 'AST_Void_of_cours'
                    ELSE 'SYM_Influence'
                 END"
            );

            $this->addSql(
                "ALTER TABLE sw_display
                 MODIFY family ENUM('symbolic','astrologie','jyotish','yijing') NOT NULL,
                 MODIFY reading_mode ENUM('SYM_Weather','SYM_Influence','SYM_AstronomicalEvent','SYM_LunationName','AST_Void_of_cours') NOT NULL"
            );
        }

        if ($schema->hasTable('sw_text_variant')) {
            $this->addSql(
                "UPDATE sw_text_variant
                 SET family = CASE
                    WHEN LOWER(TRIM(family)) IN ('astrologie','western_astro','western astro') THEN 'astrologie'
                    WHEN LOWER(TRIM(family)) = 'jyotish' THEN 'jyotish'
                    WHEN LOWER(TRIM(family)) = 'yijing' THEN 'yijing'
                    ELSE 'symbolic'
                 END"
            );

            $this->addSql(
                "UPDATE sw_text_variant
                 SET reading_mode = CASE
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_weather','weather') THEN 'SYM_Weather'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_influence','influence') THEN 'SYM_Influence'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_astronomicalevent','astronomical_event','astronomical event') THEN 'SYM_AstronomicalEvent'
                    WHEN LOWER(TRIM(reading_mode)) IN ('sym_lunationname','lunation_name','lunationname') THEN 'SYM_LunationName'
                    WHEN LOWER(TRIM(reading_mode)) IN ('ast_void_of_cours','void_of_course','void of course') THEN 'AST_Void_of_cours'
                    WHEN family = 'astrologie' THEN 'AST_Void_of_cours'
                    ELSE 'SYM_Weather'
                 END"
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('sw_display')) {
            $this->addSql(
                "ALTER TABLE sw_display
                 MODIFY family VARCHAR(50) NOT NULL,
                 MODIFY reading_mode VARCHAR(50) NOT NULL"
            );

            $this->addSql(
                "UPDATE sw_display
                 SET family = CASE
                    WHEN LOWER(TRIM(family)) = 'astrologie' THEN 'western_astro'
                    WHEN LOWER(TRIM(family)) = 'jyotish' THEN 'jyotish'
                    WHEN LOWER(TRIM(family)) = 'yijing' THEN 'yijing'
                    ELSE 'symbolic'
                 END"
            );

            $this->addSql(
                "UPDATE sw_display
                 SET reading_mode = CASE
                    WHEN reading_mode = 'SYM_Weather' THEN 'weather'
                    WHEN reading_mode = 'SYM_Influence' THEN 'influence'
                    WHEN reading_mode = 'SYM_AstronomicalEvent' THEN 'astronomical_event'
                    WHEN reading_mode = 'SYM_LunationName' THEN 'lunation_name'
                    WHEN reading_mode = 'AST_Void_of_cours' THEN 'void_of_course'
                    ELSE 'influence'
                 END"
            );

            $this->addSql(
                "ALTER TABLE sw_display
                 MODIFY family ENUM('symbolic','western_astro','jyotish','yijing') NOT NULL,
                 MODIFY reading_mode ENUM('weather','influence','astronomical_event','lunation_name','void_of_course') NOT NULL"
            );
        }

        if ($schema->hasTable('sw_text_variant')) {
            $this->addSql(
                "UPDATE sw_text_variant
                 SET family = CASE
                    WHEN LOWER(TRIM(family)) = 'astrologie' THEN 'western_astro'
                    WHEN LOWER(TRIM(family)) = 'jyotish' THEN 'jyotish'
                    WHEN LOWER(TRIM(family)) = 'yijing' THEN 'yijing'
                    ELSE 'symbolic'
                 END"
            );

            $this->addSql(
                "UPDATE sw_text_variant
                 SET reading_mode = CASE
                    WHEN reading_mode = 'SYM_Weather' THEN 'weather'
                    WHEN reading_mode = 'SYM_Influence' THEN 'influence'
                    WHEN reading_mode = 'SYM_AstronomicalEvent' THEN 'astronomical_event'
                    WHEN reading_mode = 'SYM_LunationName' THEN 'lunation_name'
                    WHEN reading_mode = 'AST_Void_of_cours' THEN 'void_of_course'
                    ELSE 'weather'
                 END"
            );
        }
    }
}

