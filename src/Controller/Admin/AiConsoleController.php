<?php

namespace App\Controller\Admin;

use App\AI\Client\LocalLlmClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiConsoleController extends AbstractController
{
    public function __construct(
        private readonly LocalLlmClientInterface $llmClient,
    ) {
    }

    #[Route('/admin/ai-console', name: 'admin_ai_console', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $prompt = trim((string) $request->request->get('prompt', ''));
        $responseText = '';
        $requestJson = '';
        $responseJson = '';
        $errorMessage = '';

        if ($prompt !== '') {
            try {
                $llmResponse = $this->llmClient->generate($prompt);

                $requestPayload = [
                    'endpoint' => $llmResponse->endpoint,
                    'payload' => $llmResponse->requestPayload,
                ];
                $responsePayload = $llmResponse->responsePayload;
                $responsePayload['http_status'] = $llmResponse->statusCode;

                $responseText = $llmResponse->text !== '' ? $llmResponse->text : 'Aucune reponse texte renvoyee.';
                $requestJson = $this->encodeJson($requestPayload);
                $responseJson = $this->encodeJson($responsePayload);
            } catch (\Throwable $exception) {
                $errorMessage = sprintf('Erreur Llama: %s', $exception->getMessage());
                $responseText = 'Erreur lors de l appel au LLM.';
                $requestJson = $this->encodeJson([
                    'prompt' => $prompt,
                    'source' => 'admin-console',
                    'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]);
            }
        }

        return $this->render('admin/ai_console.html.twig', [
            'prompt' => $prompt,
            'response_text' => $responseText,
            'request_json' => $requestJson,
            'response_json' => $responseJson,
            'error_message' => $errorMessage,
        ]);
    }

    private function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '{}' : $encoded;
    }
}
