<?php

namespace App\AI\Client;

interface LocalLlmClientInterface
{
    /**
     * Envoie un prompt a un LLM local et retourne la reponse brute + texte.
     *
     * @param array<string, mixed> $options
     */
    public function generate(string $prompt, array $options = []): LlmResponse;
}
