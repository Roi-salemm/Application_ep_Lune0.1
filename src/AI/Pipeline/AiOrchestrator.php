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
        $intentRawValue = is_string($intent['intent'] ?? null) ? $intent['intent'] : null;
        $intentNormalization = $this->intentNormalizer->normalize($intentRawValue);
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
        $cards = $this->injectSafetyRulesFromFile($cards, $language);

        $finalPrompt = $this->builder->build($intent, $cards, $userPrompt);
        $finalResp = $this->llm->generate($finalPrompt);

        $intentFinal = is_string($intent['intent'] ?? null) ? $intent['intent'] : $intentNormalization['intent'];
        $intentCoerced = $intentNormalization['coerced'] || $intentFinal !== $intentNormalization['intent'];

        return [
            'intent_json' => $intent,
            'intent_raw' => $intentNormalization['intent_raw'],
            'intent_final' => $intentFinal,
            'intent_coerced' => $intentCoerced,
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
        $intentConfidence = $intent['intent_confidence'] ?? null;
        if (!is_numeric($intentConfidence)) {
            $intentConfidence = 0.5;
        }
        $intentConfidence = (float) $intentConfidence;
        if ($intentConfidence < 0.0) {
            $intentConfidence = 0.0;
        }
        if ($intentConfidence > 1.0) {
            $intentConfidence = 1.0;
        }

        $intentFinal = $normalizedIntent;
        if ($intentConfidence < 0.55 && $intentFinal !== 'other') {
            $intentFinal = 'other';
        }

        $intent['intent'] = $intentFinal;
        $intent['intent_confidence'] = $intentConfidence;

        $intentReason = $intent['intent_reason'] ?? '';
        if (!is_string($intentReason)) {
            $intentReason = '';
        }
        $intentReason = trim($intentReason);
        if (strlen($intentReason) > 160) {
            $intentReason = substr($intentReason, 0, 160);
        }
        $intent['intent_reason'] = $intentReason;

        $intentLanguage = $intent['language'] ?? $language;
        if (is_string($intentLanguage)) {
            $intentLanguage = strtolower(trim($intentLanguage));
        } else {
            $intentLanguage = $language;
        }
        if (!in_array($intentLanguage, ['fr', 'en'], true)) {
            $intentLanguage = $language;
        }
        $intent['language'] = $intentLanguage;

        $tone = $intent['tone'] ?? null;
        if (is_string($tone)) {
            $tone = strtolower(trim($tone));
        } else {
            $tone = '';
        }
        if (!in_array($tone, ['neutral', 'polite'], true)) {
            $tone = 'polite';
        }
        $intent['tone'] = $tone;

        $constraints = $intent['constraints'] ?? [];
        if (!is_array($constraints)) {
            $constraints = [];
        }
        $maxChars = $constraints['max_chars'] ?? null;
        if (!is_numeric($maxChars)) {
            $maxChars = 400;
        } else {
            $maxChars = (int) $maxChars;
            if ($maxChars <= 0) {
                $maxChars = 400;
            } elseif (!in_array($maxChars, [200, 400, 800], true)) {
                if ($maxChars <= 250) {
                    $maxChars = 200;
                } elseif ($maxChars <= 600) {
                    $maxChars = 400;
                } else {
                    $maxChars = 800;
                }
            }
        }
        $constraints['max_chars'] = $maxChars;
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

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<int, array<string, mixed>>
     */
    private function injectSafetyRulesFromFile(array $cards, string $language): array
    {
        $root = dirname(__DIR__, 3);
        $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'safety_rules.md';
        if (!is_file($path)) {
            return $cards;
        }

        $content = trim((string) file_get_contents($path));
        if ($content === '') {
            return $cards;
        }

        $payload = [
            'card_key' => 'safety_rules',
            'title' => 'Safety Rules',
            'card_type' => 'safety',
            'content' => $content,
            'language' => $language,
        ];

        $updated = false;
        foreach ($cards as $index => $card) {
            if (!is_array($card)) {
                continue;
            }
            if (($card['card_key'] ?? null) === 'safety_rules') {
                $cards[$index] = array_merge($card, $payload);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $cards[] = $payload;
        }

        return $cards;
    }
}
