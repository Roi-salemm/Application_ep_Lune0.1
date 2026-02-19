<?php

namespace App\AI\Pipeline;

use App\AI\Client\LocalLlmClientInterface;

final class IntentParser
{
    public function __construct(
        private readonly LocalLlmClientInterface $llm,
        private readonly KnowledgeRepository $knowledgeRepo,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $userPrompt, string $language = 'fr'): array
    {
        $catalog = $this->knowledgeRepo->listCatalog($language);
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($catalogJson === false) {
            $catalogJson = '[]';
        }

        $allowedIntents = implode(', ', IntentNormalizer::ALLOWED_INTENTS);

        $parserRules = <<<TXT
Tu es un parseur. Tu dois produire UNIQUEMENT un JSON valide, sans texte autour.
Langue: {$language}.
But: extraire l'intention, les parametres et les cles de fiches a charger.
Contraintes:
- JSON strict uniquement (pas de Markdown).
- Choisis uniquement des keys presentes dans "available_knowledge_cards".
- Intents autorises: {$allowedIntents}.
- Si incertain, utilise des valeurs par defaut raisonnables.
Schema attendu:
{
  "intent": string,
  "language": "{$language}",
  "tone": string,
  "constraints": { "max_chars": number },
  "needs": { "knowledge_keys": string[], "lexicon_keys": string[] },
  "input": { "user_prompt": string }
}
TXT;

        $prompt = $parserRules
            . "\n\navailable_knowledge_cards:\n"
            . $catalogJson
            . "\n\nTexte utilisateur:\n"
            . $userPrompt;

        $resp = $this->llm->generate($prompt);
        $raw = trim($resp->text);

        if ($raw === '' && isset($resp->responsePayload['error'])) {
            throw new \RuntimeException(sprintf('Parser LLM error: %s', $resp->responsePayload['error']));
        }

        return $this->decodeJsonFromText($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFromText(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \RuntimeException('Parser JSON invalide: reponse vide.');
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('Parser JSON invalide: pas de bloc JSON.');
        }

        $json = substr($raw, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Parser JSON invalide: json_decode a echoue.');
        }

        return $data;
    }
}
