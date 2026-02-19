<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiConsoleController extends AbstractController
{
    #[Route('/admin/ai-console', name: 'admin_ai_console', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $prompt = trim((string) $request->request->get('prompt', ''));
        $responseText = '';
        $requestJson = '';
        $responseJson = '';
        $errorMessage = '';

        if ($prompt !== '') {
            $requestPayload = [
                'prompt' => $prompt,
                'source' => 'admin-console',
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ];

            $responsePayload = [
                'status' => 'stub',
                'message' => 'Integration Llama non configuree.',
                'data' => null,
            ];

            $responseText = 'Aucune integration Llama active. Branche le client LLM pour obtenir une reponse reelle.';
            $errorMessage = 'Reponse simulee: aucun client Llama actif.';
            $requestJson = $this->encodeJson($requestPayload);
            $responseJson = $this->encodeJson($responsePayload);
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
