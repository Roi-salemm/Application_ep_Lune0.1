<?php

namespace App\Controller\Admin;

use App\Entity\AppCard;
use App\Entity\AppMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ecran admin: gestion rapide des cards (listing + edition statut/type/featured + suppression).
 * Pourquoi: administrer les cards sans passer par l editeur complet.
 */
final class CardManagementController extends AbstractController
{
    private const STATUS_CHOICES = ['draft', 'published', 'hidden', 'blocked', 'scheduled'];
    private const TYPE_CHOICES = ['article', 'cycle', 'audio', 'meditation', 'information'];
    private const ACCESS_CHOICES = ['free', 'premium'];

    #[Route('/admin/cards', name: 'admin_card_management', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $cards = $entityManager->createQueryBuilder()
            ->select('c', 'm')
            ->from(AppCard::class, 'c')
            ->leftJoin('c.coverMedia', 'm')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($cards as $card) {
            if ($card instanceof AppCard) {
                $items[] = $this->serializeCard($card);
            }
        }

        return $this->render('admin/card_management.html.twig', [
            'page_title' => 'Gestion des cards',
            'page_subtitle' => 'Listing complet et edition rapide des cards.',
            'active_menu' => 'card_management',
            'cards' => $items,
            'status_choices' => self::STATUS_CHOICES,
            'type_choices' => self::TYPE_CHOICES,
            'access_choices' => self::ACCESS_CHOICES,
        ]);
    }

    #[Route('/admin/cards/{id}', name: 'admin_card_management_update', methods: ['POST'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!ctype_digit($id)) {
            return $this->jsonError('id invalide.', 400);
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $id);
        if (!$card instanceof AppCard) {
            return $this->jsonError('Card introuvable.', 404);
        }

        $payload = $this->extractPayload($request);

        $status = is_string($payload['status'] ?? null) ? trim((string) $payload['status']) : '';
        if ($status === '' || !in_array($status, self::STATUS_CHOICES, true)) {
            return $this->jsonError('Statut invalide.', 400);
        }

        $type = is_string($payload['type'] ?? null) ? trim((string) $payload['type']) : '';
        if ($type === '' || !in_array($type, self::TYPE_CHOICES, true)) {
            return $this->jsonError('Type invalide.', 400);
        }

        $featuredRank = $this->parseFeaturedRank($payload['featured_rank'] ?? null);
        if ($featuredRank === false) {
            return $this->jsonError('featured_rank invalide.', 400);
        }

        $accessLevel = is_string($payload['access_level'] ?? null) ? trim((string) $payload['access_level']) : '';
        if ($accessLevel === '' || !in_array($accessLevel, self::ACCESS_CHOICES, true)) {
            return $this->jsonError('Access level invalide.', 400);
        }

        $wasPublished = $card->getStatus() === 'published';
        $card->setStatus($status);
        if (!$wasPublished && $status === 'published' && $card->getPublishedAt() === null) {
            $card->setPublishedAt(new \DateTimeImmutable());
        }
        $card->setType($type);
        $card->setFeaturedRank($featuredRank);
        $card->setAccessLevel($accessLevel);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'card' => [
                'id' => $card->getId(),
                'status' => $card->getStatus(),
                'type' => $card->getType(),
                'featured_rank' => $card->getFeaturedRank(),
                'access_level' => $card->getAccessLevel(),
            ],
        ]);
    }

    #[Route('/admin/cards/{id}', name: 'admin_card_management_delete', methods: ['DELETE'])]
    public function delete(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!ctype_digit($id)) {
            return $this->jsonError('id invalide.', 400);
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $id);
        if (!$card instanceof AppCard) {
            return $this->jsonError('Card introuvable.', 404);
        }

        $entityManager->remove($card);
        $entityManager->flush();

        return new JsonResponse(['status' => 'ok']);
    }

    private function serializeCard(AppCard $card): array
    {
        $cover = $card->getCoverMedia();
        $coverUrl = $cover instanceof AppMedia ? '/' . ltrim((string) $cover->getAppPath(), '/') : null;

        return [
            'id' => $card->getId(),
            'type' => $card->getType(),
            'title' => $card->getTitle(),
            'description' => $card->getDescription(),
            'baseline' => $card->getBaseline(),
            'access_level' => $card->getAccessLevel(),
            'status' => $card->getStatus(),
            'featured_rank' => $card->getFeaturedRank(),
            'created_at' => $this->formatDate($card->getCreatedAt()),
            'created_at_ts' => $card->getCreatedAt()->getTimestamp(),
            'updated_at' => $this->formatDate($card->getUpdatedAt()),
            'updated_at_ts' => $card->getUpdatedAt()->getTimestamp(),
            'published_at' => $this->formatDate($card->getPublishedAt()),
            'cover_url' => $coverUrl,
        ];
    }

    private function formatDate(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d H:i');
    }

    private function extractPayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }

    /**
     * @return int|false|null
     */
    private function parseFeaturedRank(mixed $value): int|false|null
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }
        if (!is_numeric($value)) {
            return false;
        }
        $rank = (int) $value;
        if ($rank < 1) {
            return null;
        }

        return $rank;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'error' => $message,
        ], $status);
    }
}
