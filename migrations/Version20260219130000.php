<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: ajoute ia_admin_log et ia_admin_historique.
 * Pourquoi: stocker les logs et historiques du pipeline IA admin.
 */
final class Version20260219130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables ia_admin_log et ia_admin_historique.';
    }

    public function up(Schema $schema): void
    {
        $log = $schema->createTable('ia_admin_log');
        $log->addColumn('id', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
        $log->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
        $log->addColumn('created_by_user_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $log->addColumn('source', 'string', ['length' => 40, 'default' => 'admin']);
        $log->addColumn('received_json', 'json', ['notnull' => false]);
        $log->addColumn('intent_json', 'json', ['notnull' => false]);
        $log->addColumn('pipeline_json', 'json', ['notnull' => false]);
        $log->addColumn('final_prompt_text', 'text', ['notnull' => false, 'columnDefinition' => 'LONGTEXT']);
        $log->addColumn('request_payload_json', 'json', ['notnull' => false]);
        $log->addColumn('response_payload_json', 'json', ['notnull' => false]);
        $log->addColumn('notes', 'text', ['notnull' => false]);
        $log->setPrimaryKey(['id']);
        $log->addIndex(['created_at'], 'idx_ia_admin_log_created_at');
        $log->addOption('charset', 'utf8mb4');
        $log->addOption('collation', 'utf8mb4_unicode_ci');

        $hist = $schema->createTable('ia_admin_historique');
        $hist->addColumn('id', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
        $hist->addColumn('ia_admin_log_id', 'bigint', ['unsigned' => true]);
        $hist->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
        $hist->addColumn('success', 'boolean', ['default' => false]);
        $hist->addColumn('provider', 'string', ['length' => 30, 'default' => 'ollama']);
        $hist->addColumn('model_name', 'string', ['length' => 120, 'notnull' => false]);
        $hist->addColumn('prompt_name', 'string', ['length' => 150, 'notnull' => false]);
        $hist->addColumn('prompt_slug', 'string', ['length' => 180, 'notnull' => false]);
        $hist->addColumn('prompt_version', 'integer', ['unsigned' => true, 'notnull' => false]);
        $hist->addColumn('latency_ms', 'integer', ['unsigned' => true, 'notnull' => false]);
        $hist->addColumn('prompt_tokens', 'integer', ['unsigned' => true, 'notnull' => false]);
        $hist->addColumn('completion_tokens', 'integer', ['unsigned' => true, 'notnull' => false]);
        $hist->addColumn('total_tokens', 'integer', ['unsigned' => true, 'notnull' => false]);
        $hist->addColumn('error_code', 'string', ['length' => 80, 'notnull' => false]);
        $hist->addColumn('context_key', 'string', ['length' => 160, 'notnull' => false]);
        $hist->addColumn('prompt_client', 'text', ['notnull' => false]);
        $hist->addColumn('final_prompt', 'text', ['notnull' => false, 'columnDefinition' => 'LONGTEXT']);
        $hist->addColumn('response', 'text', ['notnull' => false, 'columnDefinition' => 'LONGTEXT']);
        $hist->addColumn('intent_raw', 'string', ['length' => 40, 'notnull' => false]);
        $hist->addColumn('intent', 'string', ['length' => 40, 'notnull' => false]);
        $hist->addColumn('knowledge_keys', 'json', ['notnull' => false]);
        $hist->addColumn('knowledge_keys_validated', 'json', ['notnull' => false]);
        $hist->addColumn('constraints', 'json', ['notnull' => false]);
        $hist->setPrimaryKey(['id']);
        $hist->addIndex(['created_at'], 'idx_hist_created_at');
        $hist->addIndex(['prompt_slug', 'prompt_version'], 'idx_hist_slug');
        $hist->addIndex(['success', 'created_at'], 'idx_hist_success');
        $hist->addIndex(['context_key'], 'idx_hist_context');
        $hist->addForeignKeyConstraint('ia_admin_log', ['ia_admin_log_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_hist_log');
        $hist->addOption('charset', 'utf8mb4');
        $hist->addOption('collation', 'utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('ia_admin_historique');
        $schema->dropTable('ia_admin_log');
    }
}
