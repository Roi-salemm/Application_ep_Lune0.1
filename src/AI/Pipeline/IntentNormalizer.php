<?php

namespace App\AI\Pipeline;

final class IntentNormalizer
{
    public const ALLOWED_INTENTS = [
        'informative',
        'interpretation',
        'instruction',
        'classification',
        'creative',
        'refusal',
        'other',
    ];

    private const INTENT_ALIASES = [
        'informative' => ['informative', 'information', 'explain', 'explanation', 'general', 'qa'],
        'interpretation' => ['interpretation', 'symbolic', 'reading', 'astrology', 'lunar'],
        'instruction' => ['instruction', 'howto', 'steps', 'tutorial', 'guide'],
        'classification' => ['classification', 'routing', 'intent_detection'],
        'creative' => ['creative', 'story', 'writing'],
        'refusal' => ['refusal', 'reject', 'safety'],
    ];

    /**
     * @return array{intent_raw: ?string, intent: string, coerced: bool}
     */
    public function normalize(?string $raw): array
    {
        $rawValue = is_string($raw) ? trim($raw) : '';
        if ($rawValue === '') {
            return ['intent_raw' => null, 'intent' => 'other', 'coerced' => true];
        }

        $rawLower = strtolower($rawValue);
        if (in_array($rawLower, self::ALLOWED_INTENTS, true)) {
            return ['intent_raw' => $rawValue, 'intent' => $rawLower, 'coerced' => false];
        }

        foreach (self::INTENT_ALIASES as $intent => $aliases) {
            if (in_array($rawLower, $aliases, true)) {
                return ['intent_raw' => $rawValue, 'intent' => $intent, 'coerced' => true];
            }
        }

        return ['intent_raw' => $rawValue, 'intent' => 'other', 'coerced' => true];
    }
}
