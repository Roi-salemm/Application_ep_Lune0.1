<?php

/**
 * Endpoint contenu pour l app mobile (cards, articles et cycles).
 * Pourquoi: exposer un JSON ProseMirror + medias adaptes au rendu React Native.
 * Info: filtre par status=published par defaut, CORS ouvert en lecture.
 */

namespace App\Controller\Api;

use App\Entity\AppArticleContent;
use App\Entity\AppCard;
use App\Entity\AppCycleModule;
use App\Entity\AppCycleModuleItem;
use App\Entity\AppMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/content')]
final class ContentApiController extends AbstractController
{
    #[Route('/cards', name: 'api_content_cards', methods: ['GET', 'OPTIONS'])]
    public function listCards(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        $type = trim((string) $request->query->get('type', ''));
        if ($type !== '' && !in_array($type, ['article', 'cycle', 'audio', 'meditation', 'information'], true)) {
            return new JsonResponse(['error' => 'type invalide.'], 400, $this->corsHeaders());
        }

        $featuredOnly = (string) $request->query->get('featured', '') === '1';
        $limit = $this->clampInt($request->query->get('limit', 50), 1, 100);
        $offset = $this->clampInt($request->query->get('offset', 0), 0, 10000);

        $now = new \DateTimeImmutable('now');

        $qb = $entityManager->createQueryBuilder()
            ->select('c', 'm')
            ->addSelect('CASE WHEN c.featuredRank IS NULL THEN 1 ELSE 0 END AS HIDDEN featured_null')
            ->from(AppCard::class, 'c')
            ->leftJoin('c.coverMedia', 'm')
            ->where('c.status = :status')
            ->andWhere('(c.publishedAt IS NULL OR c.publishedAt <= :now)')
            ->setParameter('status', 'published')
            ->setParameter('now', $now);

        if ($type !== '') {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }

        if ($featuredOnly) {
            $qb->andWhere('c.featuredRank IS NOT NULL');
        }

        $qb
            ->orderBy('featured_null', 'ASC')
            ->addOrderBy('c.featuredRank', 'ASC')
            ->addOrderBy('c.publishedAt', 'DESC');

        $cards = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($cards as $card) {
            if ($card instanceof AppCard) {
                $items[] = $this->serializeCardListing($card);
            }
        }

        return new JsonResponse(
            [
                'count' => count($items),
                'limit' => $limit,
                'offset' => $offset,
                'items' => $items,
            ],
            200,
            $this->corsHeaders()
        );
    }

    #[Route('/cards/{id}', name: 'api_content_card_show', methods: ['GET', 'OPTIONS'])]
    public function showCard(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $this->corsHeaders());
        }

        if (!ctype_digit($id)) {
            return new JsonResponse(['error' => 'id invalide.'], 400, $this->corsHeaders());
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $id);
        if (!$card instanceof AppCard) {
            return new JsonResponse(['error' => 'Card introuvable.'], 404, $this->corsHeaders());
        }

        $includeDraft = (string) $request->query->get('include_draft', '') === '1';
        if ($card->getStatus() !== 'published' && !$includeDraft) {
            return new JsonResponse(['error' => 'Card non publiee.'], 404, $this->corsHeaders());
        }

        $payload = [
            'card' => $this->serializeCard($card),
            'article' => null,
            'modules' => [],
        ];

        if (in_array($card->getType(), ['article', 'audio', 'meditation', 'information'], true)) {
            $content = $entityManager->getRepository(AppArticleContent::class)->findOneBy(['card' => $card]);
            $payload['article'] = [
                'content_json' => $content instanceof AppArticleContent ? $content->getBodyJson() : [],
                'reading_minutes' => $content?->getReadingMinutes(),
                'hero_media' => $content?->getHeroMedia() ? $this->serializeMedia($content->getHeroMedia()) : null,
            ];

            return new JsonResponse($payload, 200, $this->corsHeaders());
        }

        $modules = $entityManager->getRepository(AppCycleModule::class)
            ->createQueryBuilder('m')
            ->where('m.cycleCard = :card')
            ->setParameter('card', $card)
            ->orderBy('m.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();

        $itemRepo = $entityManager->getRepository(AppCycleModuleItem::class);
        foreach ($modules as $module) {
            if (!$module instanceof AppCycleModule) {
                continue;
            }
            $items = $itemRepo->createQueryBuilder('i')
                ->where('i.module = :module')
                ->setParameter('module', $module)
                ->orderBy('i.orderIndex', 'ASC')
                ->getQuery()
                ->getResult();

            $payload['modules'][] = [
                'id' => $module->getId(),
                'title' => $module->getTitle(),
                'baseline' => $module->getBaseline(),
                'order_index' => $module->getOrderIndex(),
                'is_published' => $module->isPublished(),
                'items' => array_map(fn ($item) => $this->serializeModuleItem($item), $items),
            ];
        }

        return new JsonResponse($payload, 200, $this->corsHeaders());
    }

    private function serializeCard(AppCard $card): array
    {
        return [
            'id' => $card->getId(),
            'slug' => $card->getSlug(),
            'type' => $card->getType(),
            'title' => $card->getTitle(),
            'baseline' => $card->getBaseline(),
            'description' => $card->getDescription(),
            'access_level' => $card->getAccessLevel(),
            'status' => $card->getStatus(),
            'featured_rank' => $card->getFeaturedRank(),
            'created_at' => $this->formatDate($card->getCreatedAt()),
            'published_at' => $this->formatDate($card->getPublishedAt()),
            'updated_at' => $this->formatDate($card->getUpdatedAt()),
            'cover' => $card->getCoverMedia() ? $this->serializeMedia($card->getCoverMedia()) : null,
        ];
    }

    private function serializeCardListing(AppCard $card): array
    {
        $cover = $card->getCoverMedia();

        return [
            'id' => $card->getId(),
            'type' => $card->getType(),
            'slug' => $card->getSlug(),
            'title' => $card->getTitle(),
            'baseline' => $card->getBaseline(),
            'description' => $card->getDescription(),
            'accessLevel' => $card->getAccessLevel(),
            'status' => $card->getStatus(),
            'featuredRank' => $card->getFeaturedRank(),
            'createdAt' => $this->formatDate($card->getCreatedAt()),
            'publishedAt' => $this->formatDate($card->getPublishedAt()),
            'updatedAt' => $this->formatDate($card->getUpdatedAt()),
            'coverMedia' => $cover instanceof AppMedia
                ? [
                    'id' => $cover->getId(),
                    'url' => $this->buildMediaUrl($cover),
                ]
                : null,
        ];
    }

    private function serializeModuleItem(mixed $item): array
    {
        if (!$item instanceof AppCycleModuleItem) {
            return [];
        }

        return [
            'id' => $item->getId(),
            'item_type' => $item->getItemType(),
            'order_index' => $item->getOrderIndex(),
            'title_override' => $item->getTitleOverride(),
            'is_free_preview' => $item->isFreePreview(),
            'external_url' => $item->getExternalUrl(),
            'content_json' => $item->getContentJson(),
            'ref_card' => $item->getRefCard() ? $this->serializeCard($item->getRefCard()) : null,
            'ref_media' => $item->getRefMedia() ? $this->serializeMedia($item->getRefMedia()) : null,
        ];
    }

    private function serializeMedia(AppMedia $media): array
    {
        return [
            'id' => $media->getId(),
            'img_mini' => $this->buildVariant(
                $media->getImgMiniPath(),
                $media->getImgMiniMime(),
                $media->getImgMiniWidth(),
                $media->getImgMiniHeight(),
                $media->getImgMiniSize()
            ),
            'img_tel' => $this->buildVariant(
                $media->getImgTelPath(),
                $media->getImgTelMime(),
                $media->getImgTelWidth(),
                $media->getImgTelHeight(),
                $media->getImgTelSize()
            ),
            'img_tab' => $this->buildVariant(
                $media->getImgTabPath(),
                $media->getImgTabMime(),
                $media->getImgTabWidth(),
                $media->getImgTabHeight(),
                $media->getImgTabSize()
            ),
        ];
    }

    private function buildMediaUrl(AppMedia $media): ?string
    {
        $path = trim((string) $media->getAppPath());
        if ($path === '') {
            return null;
        }

        return '/' . ltrim($path, '/');
    }

    private function buildVariant(?string $path, ?string $mime, ?int $width, ?int $height, ?int $size): ?array
    {
        if ($path === null) {
            return null;
        }

        return [
            'url' => '/' . ltrim($path, '/'),
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'size' => $size,
        ];
    }

    private function formatDate(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int) $value : $min;
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];
    }
}
