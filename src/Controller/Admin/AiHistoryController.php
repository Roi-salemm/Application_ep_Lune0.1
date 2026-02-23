<?php

namespace App\Controller\Admin;

use App\Entity\IaAdminHistorique;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiHistoryController extends AbstractController
{
    #[Route('/admin/ai/history', name: 'admin_ai_history', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 20);
        $limit = max(5, min(100, $limit));

        $success = $request->query->get('success', '');
        $intent = trim((string) $request->query->get('intent', ''));
        $slug = trim((string) $request->query->get('slug', ''));
        $dateFrom = trim((string) $request->query->get('from', ''));
        $dateTo = trim((string) $request->query->get('to', ''));
        $stage = trim((string) $request->query->get('stage', 'generation'));

        $qb = $entityManager->createQueryBuilder()
            ->select('h', 'l')
            ->from(IaAdminHistorique::class, 'h')
            ->join('h.log', 'l')
            ->orderBy('h.createdAt', 'DESC');

        if ($success === '1' || $success === '0') {
            $qb->andWhere('h.success = :success')->setParameter('success', $success === '1');
        }

        if ($stage !== '') {
            $qb->andWhere('h.stage = :stage')->setParameter('stage', $stage);
        }

        if ($intent !== '') {
            $qb->andWhere('h.intent = :intent')->setParameter('intent', $intent);
        }

        if ($slug !== '') {
            $qb->andWhere('h.promptSlug = :slug')->setParameter('slug', $slug);
        }

        if ($dateFrom !== '') {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
            if ($from instanceof \DateTimeImmutable) {
                $qb->andWhere('h.createdAt >= :from')->setParameter('from', $from->setTime(0, 0, 0));
            }
        }

        if ($dateTo !== '') {
            $to = \DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);
            if ($to instanceof \DateTimeImmutable) {
                $qb->andWhere('h.createdAt <= :to')->setParameter('to', $to->setTime(23, 59, 59));
            }
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(h.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);

        $histories = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/ai_history/index.html.twig', [
            'histories' => $histories,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'limit' => $limit,
            'filters' => [
                'success' => $success,
                'intent' => $intent,
                'slug' => $slug,
                'from' => $dateFrom,
                'to' => $dateTo,
                'stage' => $stage,
            ],
        ]);
    }

    #[Route('/admin/ai/history/{id}', name: 'admin_ai_history_show', methods: ['GET'])]
    public function show(IaAdminHistorique $history): Response
    {
        return $this->render('admin/ai_history/show.html.twig', [
            'history' => $history,
            'log' => $history->getLog(),
        ]);
    }

    #[Route('/admin/ai/history/{id}/json', name: 'admin_ai_history_json', methods: ['GET'])]
    public function historyJson(IaAdminHistorique $history): JsonResponse
    {
        $log = $history->getLog();

        return new JsonResponse([
            'history' => [
                'id' => $history->getId(),
                'created_at' => $history->getCreatedAt()->format('Y-m-d H:i:s'),
                'success' => $history->isSuccess(),
                'stage' => $history->getStage(),
                'pipeline_id' => $history->getPipelineId(),
                'fingerprint' => $history->getFingerprint(),
                'provider' => $history->getProvider(),
                'model_name' => $history->getModelName(),
                'prompt_name' => $history->getPromptName(),
                'prompt_slug' => $history->getPromptSlug(),
                'prompt_version' => $history->getPromptVersion(),
                'latency_ms' => $history->getLatencyMs(),
                'prompt_tokens' => $history->getPromptTokens(),
                'completion_tokens' => $history->getCompletionTokens(),
                'total_tokens' => $history->getTotalTokens(),
                'error_code' => $history->getErrorCode(),
                'context_key' => $history->getContextKey(),
                'prompt_client' => $history->getPromptClient(),
                'final_prompt' => $history->getFinalPrompt(),
                'response' => $history->getResponse(),
                'intent_raw' => $history->getIntentRaw(),
                'intent' => $history->getIntent(),
                'knowledge_keys' => $history->getKnowledgeKeys(),
                'knowledge_keys_validated' => $history->getKnowledgeKeysValidated(),
                'constraints' => $history->getConstraints(),
            ],
            'log' => [
                'received_json' => $log->getReceivedJson(),
                'intent_json' => $log->getIntentJson(),
                'pipeline_json' => $log->getPipelineJson(),
                'final_prompt_text' => $log->getFinalPromptText(),
                'request_payload_json' => $log->getRequestPayloadJson(),
                'response_payload_json' => $log->getResponsePayloadJson(),
                'notes' => $log->getNotes(),
            ],
        ]);
    }

    #[Route('/admin/ai/history/{id}/delete', name: 'admin_ai_history_delete', methods: ['POST'])]
    public function delete(Request $request, IaAdminHistorique $history, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_ai_history_' . $history->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_ai_history_show', ['id' => $history->getId()]);
        }

        $log = $history->getLog();
        $entityManager->remove($log);
        $entityManager->flush();

        $this->addFlash('success', 'Historique AI supprime.');

        return $this->redirectToRoute('admin_ai_history');
    }
}
