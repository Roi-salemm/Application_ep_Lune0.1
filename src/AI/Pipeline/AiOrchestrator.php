<?php

namespace App\AI\Pipeline;

use App\AI\Client\LocalLlmClientInterface;

final class AiOrchestrator
{
    public function __construct(
        private readonly IntentParser $parser,
        private readonly KnowledgeRepository $knowledgeRepo,
        private readonly PromptBuilder $builder,
        private readonly IntentNormalizer $intentNormalizer,
        private readonly LocalLlmClientInterface $llm,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $userPrompt, string $language = 'fr'): array
    {
        $intent = $this->parser->parse($userPrompt, $language);
        $intentNormalization = $this->intentNormalizer->normalize($intent['intent'] ?? null);
        $intent = $this->normalizeIntent($intent, $userPrompt, $language, $intentNormalization['intent']);

        $catalog = $this->knowledgeRepo->listCatalog($language);
        $allowed = array_flip(array_map(
            static fn (array $card) => is_string($card['card_key'] ?? null) ? $card['card_key'] : '',
            $catalog
        ));
        unset($allowed['']);

        $wantedKeys = $intent['needs']['knowledge_keys'] ?? [];
        $wantedLexiconKeys = $intent['needs']['lexicon_keys'] ?? [];

        $validKnowledgeKeys = $this->filterKeys(is_array($wantedKeys) ? $wantedKeys : [], $allowed);
        $validLexiconKeys = $this->filterKeys(is_array($wantedLexiconKeys) ? $wantedLexiconKeys : [], $allowed);

        if (!in_array('safety_rules', $validKnowledgeKeys, true)) {
            $validKnowledgeKeys[] = 'safety_rules';
        }

        $allKeys = array_values(array_unique(array_merge($validKnowledgeKeys, $validLexiconKeys)));
        $cards = $this->knowledgeRepo->findByKeys($allKeys, $language);

        $finalPrompt = $this->builder->build($intent, $cards, $userPrompt);
        $finalResp = $this->llm->generate($finalPrompt);

        return [
            'intent_json' => $intent,
            'intent_raw' => $intentNormalization['intent_raw'],
            'intent_final' => $intentNormalization['intent'],
            'intent_coerced' => $intentNormalization['coerced'],
            'knowledge_keys_validated' => $validKnowledgeKeys,
            'lexicon_keys_validated' => $validLexiconKeys,
            'knowledge_cards' => $cards,
            'final_prompt' => $finalPrompt,
            'final_response' => $finalResp->text,
            'final_raw' => $finalResp->responsePayload,
            'final_request_payload' => $finalResp->requestPayload,
            'final_endpoint' => $finalResp->endpoint,
            'final_status_code' => $finalResp->statusCode,
        ];
    }

    /**
     * @param array<int, mixed> $keys
     * @param array<string, int> $allowed
     * @return string[]
     */
    private function filterKeys(array $keys, array $allowed): array
    {
        $clean = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $trimmed = trim($key);
            if ($trimmed === '' || !isset($allowed[$trimmed])) {
                continue;
            }
            $clean[] = $trimmed;
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function normalizeIntent(array $intent, string $userPrompt, string $language, string $normalizedIntent): array
    {
        $intent['intent'] = $normalizedIntent;
        $intent['language'] = $language;

        $tone = $intent['tone'] ?? null;
        $intent['tone'] = is_string($tone) && $tone !== '' ? $tone : 'neutral';

        $constraints = $intent['constraints'] ?? [];
        if (!is_array($constraints)) {
            $constraints = [];
        }
        if (!isset($constraints['max_chars']) || !is_numeric($constraints['max_chars'])) {
            $constraints['max_chars'] = 900;
        } else {
            $constraints['max_chars'] = (int) $constraints['max_chars'];
        }
        $intent['constraints'] = $constraints;

        $needs = $intent['needs'] ?? [];
        if (!is_array($needs)) {
            $needs = [];
        }
        $needs['knowledge_keys'] = is_array($needs['knowledge_keys'] ?? null) ? $needs['knowledge_keys'] : [];
        $needs['lexicon_keys'] = is_array($needs['lexicon_keys'] ?? null) ? $needs['lexicon_keys'] : [];
        $intent['needs'] = $needs;

        $input = $intent['input'] ?? [];
        if (!is_array($input)) {
            $input = [];
        }
        $input['user_prompt'] = $userPrompt;
        $intent['input'] = $input;

        return $intent;
    }
}
