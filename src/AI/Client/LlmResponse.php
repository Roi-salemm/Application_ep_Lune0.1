<?php

namespace App\AI\Client;

final class LlmResponse
{
    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responsePayload
     */
    public function __construct(
        public readonly array $requestPayload,
        public readonly array $responsePayload,
        public readonly string $text,
        public readonly int $statusCode,
        public readonly string $endpoint,
    ) {
    }
}
