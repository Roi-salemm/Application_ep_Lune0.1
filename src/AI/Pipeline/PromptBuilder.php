<?php

namespace App\AI\Pipeline;

final class PromptBuilder
{
    /**
     * @param array<string, mixed> $intentJson
     * @param array<int, array<string, mixed>> $cards
     */
    public function build(array $intentJson, array $cards, string $userPrompt): string
    {
        $globalRules = <<<TXT
Regles globales:
- Reponds en francais.
- Style sobre, phrases claires.
- Pas de conseils medicaux.
TXT;

        $cardsBlock = '';
        foreach ($cards as $card) {
            $key = is_string($card['card_key'] ?? null) ? $card['card_key'] : 'unknown';
            $type = is_string($card['card_type'] ?? null) ? $card['card_type'] : 'unknown';
            $title = is_string($card['title'] ?? null) ? $card['title'] : 'Sans titre';
            $content = is_string($card['content'] ?? null) ? $card['content'] : '';

            $cardsBlock .= "\n--- CARD {$key} ({$type}) : {$title} ---\n{$content}\n";
        }

        $intentBlock = json_encode($intentJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($intentBlock === false) {
            $intentBlock = '{}';
        }

        return $globalRules
            . "\n\nFiches:\n" . ($cardsBlock !== '' ? $cardsBlock : '(aucune)')
            . "\n\nIntent JSON:\n" . $intentBlock
            . "\n\nDemande utilisateur:\n" . $userPrompt;
    }
}
