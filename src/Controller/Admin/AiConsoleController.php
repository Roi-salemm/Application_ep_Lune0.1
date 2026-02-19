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
        $isPost = $request->isMethod('POST');
        $prompt = trim((string) $request->request->get('prompt', ''));
        $responseText = '';
        $requestJson = '';
        $responseJson = '';
        $errorMessage = '';

        if ($isPost) {
            $requestJson = $this->encodeJson([
                'received_post' => $request->request->all(),
                'received_prompt_raw' => $request->request->get('prompt', null),
                'received_prompt_trimmed' => $prompt,
                'content_type' => $request->headers->get('content-type'),
            ]);
        }

        if ($isPost && $prompt === '') {
            $errorMessage = 'Prompt vide (Symfony ne recoit pas de champ "prompt" ou il est vide).';
            $responseText = 'Aucune requete envoyee a Ollama.';
            return $this->render('admin/ai_console.html.twig', [
                'prompt' => $prompt,
                'response_text' => $responseText,
                'request_json' => $requestJson,
                'response_json' => $responseJson,
                'error_message' => $errorMessage,
            ]);
        }

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
                $responseJson = $this->encodeJson(['exception' => $exception->getMessage()]);
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
