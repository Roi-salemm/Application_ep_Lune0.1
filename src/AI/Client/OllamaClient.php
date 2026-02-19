<?php

namespace App\AI\Client;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OllamaClient implements LocalLlmClientInterface
{
    private string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:OLLAMA_BASE_URL)%')] string $baseUrl,
        #[Autowire('%env(string:OLLAMA_MODEL)%')] private readonly string $model,
    ) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
    }

    public function generate(string $prompt, array $options = []): LlmResponse
    {
        $payload = array_merge([
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ], $options);

        $endpoint = $this->baseUrl . '/api/generate';

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Reponse Ollama invalide (JSON attendu).');
        }

        $text = '';
        if (isset($data['response']) && is_string($data['response'])) {
            $text = $data['response'];
        } elseif (isset($data['message']['content']) && is_string($data['message']['content'])) {
            $text = $data['message']['content'];
        }

        return new LlmResponse(
            requestPayload: $payload,
            responsePayload: $data,
            text: $text,
            statusCode: $statusCode,
            endpoint: $endpoint,
        );
    }
}
