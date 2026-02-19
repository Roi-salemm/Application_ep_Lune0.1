<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: ajoute ai_knowledge_card et des fiches de base.
 * Pourquoi: stocker les fiches stables pour le pipeline IA.
 */
final class Version20260219120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ai_knowledge_card et des fiches minimales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE ai_knowledge_card (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                card_key VARCHAR(120) NOT NULL,
                title VARCHAR(255) NOT NULL,
                card_type VARCHAR(32) NOT NULL DEFAULT \'rule\',
                content TEXT NOT NULL,
                tags JSON DEFAULT NULL,
                domain VARCHAR(80) DEFAULT NULL,
                language VARCHAR(2) NOT NULL DEFAULT \'fr\',
                version INT UNSIGNED NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                priority SMALLINT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uq_ai_knowledge_key_lang (card_key, language),
                INDEX idx_ai_knowledge_active (is_active),
                INDEX idx_ai_knowledge_type (card_type),
                INDEX idx_ai_knowledge_domain (domain),
                INDEX idx_ai_knowledge_priority (priority),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4'
        );

        $this->addSql(
            "INSERT INTO ai_knowledge_card (card_key, title, card_type, content, domain, language, version, is_active, priority, created_at, updated_at) VALUES
            ('axes_lune2', 'Axes Lune 2', 'rule', 'Regles editoriales et posture de reponse pour la console IA.', 'core', 'fr', 1, 1, 10, NOW(), NOW()),
            ('lune_cards_A', 'Format Lune Cards A', 'format', 'Format de reponse: sections courtes, puces et conclusion concise.', 'core', 'fr', 1, 1, 20, NOW(), NOW()),
            ('safety_rules', 'Safety Rules', 'safety', 'Regles de securite: refuser poliment les demandes interdites.', 'core', 'fr', 1, 1, 5, NOW(), NOW())"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_knowledge_card');
    }
}
