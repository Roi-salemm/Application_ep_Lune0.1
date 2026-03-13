<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verrouille la relation 1:1 entre sw_schedule et sw_display.
 * Pourquoi: garantir la regle metier "1 texte = 1 ligne sw_display + 1 ligne sw_content + 1 ligne sw_schedule".
 * Info: en cas de display partage par plusieurs schedules, la migration clone display/content pour conserver chaque texte isole.
 */
final class Version20260313120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize schedule/display to 1:1 and add unique index on sw_schedule.display_id.';
    }

    public function up(Schema $schema): void
    {
        if (
            !$schema->hasTable('sw_display')
            || !$schema->hasTable('sw_content')
            || !$schema->hasTable('sw_schedule')
        ) {
            return;
        }
        $scheduleTable = $schema->getTable('sw_schedule');

        // Table temporaire: un enregistrement par schedule a relier a un nouveau display.
        $this->addSql(
            "CREATE TEMPORARY TABLE tmp_sw_schedule_display_map (
                schedule_id BIGINT UNSIGNED NOT NULL,
                old_display_id BIGINT UNSIGNED NOT NULL,
                new_display_code VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                new_display_id BIGINT UNSIGNED DEFAULT NULL,
                PRIMARY KEY(schedule_id),
                UNIQUE KEY uq_tmp_sw_schedule_display_map_code (new_display_code)
            ) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            "INSERT INTO tmp_sw_schedule_display_map (schedule_id, old_display_id, new_display_code)
             SELECT s1.id, s1.display_id, CONCAT(LEFT(d.code, 120), '__s', s1.id)
             FROM sw_schedule s1
             INNER JOIN sw_display d ON d.id = s1.display_id
             INNER JOIN sw_schedule s2
                 ON s2.display_id = s1.display_id
                 AND s2.id <= s1.id
             GROUP BY s1.id, s1.display_id, d.code
             HAVING COUNT(s2.id) > 1"
        );

        // Clone des displays partages.
        $this->addSql(
            "INSERT INTO sw_display (code, family, reading_mode, lang, comment, is_active, created_at_utc, updated_at_utc)
             SELECT map.new_display_code, d.family, d.reading_mode, d.lang, d.comment, d.is_active, d.created_at_utc, d.updated_at_utc
             FROM tmp_sw_schedule_display_map map
             INNER JOIN sw_display d ON d.id = map.old_display_id"
        );

        $this->addSql(
            "UPDATE tmp_sw_schedule_display_map map
             INNER JOIN sw_display d
                 ON d.code COLLATE utf8mb4_unicode_ci = map.new_display_code COLLATE utf8mb4_unicode_ci
             SET map.new_display_id = d.id"
        );

        // Repointage schedule -> nouveau display.
        $this->addSql(
            "UPDATE sw_schedule s
             INNER JOIN tmp_sw_schedule_display_map map ON map.schedule_id = s.id
             SET s.display_id = map.new_display_id"
        );

        // Clone du contenu pour conserver 1 content par texte relie au display clone.
        $this->addSql(
            "INSERT INTO sw_content (
                 display_id, version_no, status, is_current, is_validated, content_json, schema_version,
                 comment, editorial_notes, ai_model, ai_prompt_version, created_at_utc, updated_at_utc, validated_at_utc
             )
             SELECT map.new_display_id, c.version_no, c.status, c.is_current, c.is_validated, c.content_json, c.schema_version,
                    c.comment, c.editorial_notes, c.ai_model, c.ai_prompt_version, c.created_at_utc, c.updated_at_utc, c.validated_at_utc
             FROM tmp_sw_schedule_display_map map
             INNER JOIN sw_schedule s ON s.id = map.schedule_id
             INNER JOIN sw_content c ON c.id = s.content_id"
        );

        // Repointage schedule -> content clone (lie au nouveau display).
        $this->addSql(
            "UPDATE sw_schedule s
             INNER JOIN tmp_sw_schedule_display_map map ON map.schedule_id = s.id
             INNER JOIN sw_content c ON c.display_id = map.new_display_id
             SET s.content_id = c.id"
        );

        // Verrou 1:1 de schema.
        if (!$scheduleTable->hasIndex('uq_sw_schedule_display')) {
            $this->addSql('CREATE UNIQUE INDEX uq_sw_schedule_display ON sw_schedule (display_id)');
        }

        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_sw_schedule_display_map');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('sw_schedule')) {
            return;
        }
        $scheduleTable = $schema->getTable('sw_schedule');

        // Le retrait du verrou de schema est reversible, pas la fusion des lignes clonees.
        if ($scheduleTable->hasIndex('uq_sw_schedule_display')) {
            $this->addSql('DROP INDEX uq_sw_schedule_display ON sw_schedule');
        }
    }
}
