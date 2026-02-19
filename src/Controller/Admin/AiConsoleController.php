<?php

namespace App\Controller\Admin;

use App\AI\Pipeline\AiOrchestrator;
use App\Entity\IaAdminHistorique;
use App\Entity\IaAdminLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiConsoleController extends AbstractController
{
    public function __construct(
        private readonly AiOrchestrator $orchestrator,
        private readonly EntityManagerInterface $entityManager,
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
        $receivedPost = null;

        if ($isPost) {
            $receivedPost = [
                'received_post' => $request->request->all(),
                'received_prompt_raw' => $request->request->get('prompt', null),
                'received_prompt_trimmed' => $prompt,
                'content_type' => $request->headers->get('content-type'),
            ];
            $requestJson = $this->encodeJson($receivedPost);
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
                $result = $this->orchestrator->run($prompt, 'fr');

                $responseText = is_string($result['final_response'] ?? null) ? trim((string) $result['final_response']) : '';
                $finalRaw = is_array($result['final_raw'] ?? null) ? $result['final_raw'] : [];
                if ($responseText === '' && isset($finalRaw['error'])) {
                    $responseText = sprintf('Erreur Llama: %s', $finalRaw['error']);
                }
                if ($responseText === '') {
                    $responseText = 'Aucune reponse texte renvoyee.';
                }

                $requestJson = $this->encodeJson(array_filter([
                    'received' => $receivedPost,
                    'intent_json' => $result['intent_json'] ?? null,
                    'knowledge_keys_validated' => $result['knowledge_keys_validated'] ?? null,
                    'lexicon_keys_validated' => $result['lexicon_keys_validated'] ?? null,
                    'final_prompt' => $result['final_prompt'] ?? null,
                ], static fn ($value) => $value !== null));

                $responseJson = $this->encodeJson([
                    'final_raw' => $finalRaw,
                    'final_response' => $result['final_response'] ?? null,
                ]);

                try {
                    $this->persistAdminHistory($prompt, $receivedPost, $result, $finalRaw, $responseText);
                } catch (\Throwable $logException) {
                    $errorMessage = sprintf('Erreur log IA: %s', $logException->getMessage());
                }
            } catch (\Throwable $exception) {
                $errorMessage = sprintf('Erreur Llama: %s', $exception->getMessage());
                $responseText = 'Erreur lors de l appel au LLM.';
                $responseJson = $this->encodeJson(['exception' => $exception->getMessage()]);
                $requestJson = $this->encodeJson(array_filter([
                    'received' => $receivedPost,
                    'prompt' => $prompt,
                    'source' => 'admin-console',
                    'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ], static fn ($value) => $value !== null));
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

    /**
     * @param array<string, mixed>|null $receivedPost
     * @param array<string, mixed> $result
     * @param array<string, mixed> $finalRaw
     */
    private function persistAdminHistory(
        string $prompt,
        ?array $receivedPost,
        array $result,
        array $finalRaw,
        string $responseText
    ): void {
        $intentJson = is_array($result['intent_json'] ?? null) ? $result['intent_json'] : null;
        $pipelineJson = [
            'knowledge_keys_validated' => $result['knowledge_keys_validated'] ?? [],
            'lexicon_keys_validated' => $result['lexicon_keys_validated'] ?? [],
            'constraints' => $intentJson['constraints'] ?? null,
            'intent' => $result['intent_final'] ?? null,
            'intent_raw' => $result['intent_raw'] ?? null,
            'intent_coerced' => $result['intent_coerced'] ?? null,
        ];

        $log = new IaAdminLog();
        $log->setSource('admin');
        $log->setReceivedJson($receivedPost);
        $log->setIntentJson($intentJson);
        $log->setPipelineJson($pipelineJson);
        $log->setFinalPromptText(is_string($result['final_prompt'] ?? null) ? $result['final_prompt'] : null);
        $log->setRequestPayloadJson($this->buildRequestPayload($result));
        $log->setResponsePayloadJson($finalRaw);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $history = new IaAdminHistorique($log);
        $history->setSuccess(!$this->hasProviderError($finalRaw));
        $history->setProvider('ollama');
        $history->setModelName($this->extractModelName($result, $finalRaw));
        $history->setPromptClient($prompt);
        $history->setFinalPrompt(is_string($result['final_prompt'] ?? null) ? $result['final_prompt'] : null);
        $history->setResponse($responseText !== '' ? $responseText : null);
        $history->setIntentRaw(is_string($result['intent_raw'] ?? null) ? $result['intent_raw'] : null);
        $history->setIntent(is_string($result['intent_final'] ?? null) ? $result['intent_final'] : null);
        $history->setKnowledgeKeys(
            is_array($intentJson['needs']['knowledge_keys'] ?? null) ? $intentJson['needs']['knowledge_keys'] : null
        );
        $history->setKnowledgeKeysValidated(is_array($result['knowledge_keys_validated'] ?? null) ? $result['knowledge_keys_validated'] : null);
        $history->setConstraints(is_array($intentJson['constraints'] ?? null) ? $intentJson['constraints'] : null);
        $history->setContextKey(is_string($result['intent_final'] ?? null) ? $result['intent_final'] : null);

        $this->applyUsageMetrics($history, $finalRaw);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function buildRequestPayload(array $result): ?array
    {
        $payload = is_array($result['final_request_payload'] ?? null) ? $result['final_request_payload'] : null;
        if ($payload === null) {
            return null;
        }

        return array_filter([
            'endpoint' => $result['final_endpoint'] ?? null,
            'status_code' => $result['final_status_code'] ?? null,
            'payload' => $payload,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $finalRaw
     */
    private function hasProviderError(array $finalRaw): bool
    {
        return isset($finalRaw['error']) && is_string($finalRaw['error']) && trim($finalRaw['error']) !== '';
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $finalRaw
     */
    private function extractModelName(array $result, array $finalRaw): ?string
    {
        $payload = $result['final_request_payload'] ?? null;
        if (is_array($payload) && is_string($payload['model'] ?? null)) {
            return $payload['model'];
        }
        if (is_string($finalRaw['model'] ?? null)) {
            return $finalRaw['model'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $finalRaw
     */
    private function applyUsageMetrics(IaAdminHistorique $history, array $finalRaw): void
    {
        $totalDuration = $finalRaw['total_duration'] ?? null;
        if (is_numeric($totalDuration)) {
            $history->setLatencyMs((int) round(((float) $totalDuration) / 1000000));
        }

        $promptEval = $finalRaw['prompt_eval_count'] ?? null;
        $evalCount = $finalRaw['eval_count'] ?? null;

        if (is_numeric($promptEval)) {
            $history->setPromptTokens((int) $promptEval);
        }

        if (is_numeric($evalCount)) {
            $history->setCompletionTokens((int) $evalCount);
        }

        if (is_numeric($promptEval) && is_numeric($evalCount)) {
            $history->setTotalTokens((int) $promptEval + (int) $evalCount);
        }

        if ($this->hasProviderError($finalRaw)) {
            $history->setErrorCode((string) $finalRaw['error']);
        }
    }
}
