<?php

namespace App\AI\Pipeline;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class KnowledgeRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCatalog(string $language = 'fr'): array
    {
        $sql = 'SELECT card_key, title, card_type, domain
                FROM ai_knowledge_card
                WHERE is_active = 1 AND language = ?
                ORDER BY priority ASC, card_key ASC';

        return $this->db->fetchAllAssociative($sql, [$language]);
    }

    /**
     * @param string[] $keys
     * @return array<int, array<string, mixed>>
     */
    public function findByKeys(array $keys, string $language = 'fr'): array
    {
        $keys = array_values(array_filter($keys, static fn ($key) => is_string($key) && trim($key) !== ''));
        if ($keys === []) {
            return [];
        }

        $sql = 'SELECT card_key, title, card_type, content
                FROM ai_knowledge_card
                WHERE is_active = 1 AND language = ? AND card_key IN (?)';

        return $this->db->fetchAllAssociative(
            $sql,
            [$language, $keys],
            [ParameterType::STRING, ArrayParameterType::STRING]
        );
    }
}
