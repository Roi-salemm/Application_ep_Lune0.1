<?php

namespace App\Controller\Admin;

use App\Entity\AppArticleContent;
use App\Entity\AppCard;
use App\Entity\AppCycleModule;
use App\Entity\AppCycleModuleItem;
use App\Entity\AppMedia;
use App\Service\Media\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur admin: creation de cards, modules, contenu Tiptap et media via AJAX.
 * Pourquoi: centraliser le workflow "maker" dans une interface unifiee.
 * Info: routes JSON + upload image, avec persistance Doctrine dans les tables app_*.
 */
final class ArticleEditorController extends AbstractController
{
    private const CARD_TYPES = ['article', 'cycle', 'audio', 'meditation', 'information'];
    private const ARTICLE_TYPES = ['article', 'audio', 'meditation', 'information'];
    private const ACCESS_LEVELS = ['free', 'premium'];
    private const SLUG_MAX_LENGTH = 190;
    #[Route('/admin/article-editor', name: 'admin_article_editor', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/article_editor.html.twig', [
            'page_title' => 'Article editor',
            'page_subtitle' => 'Creation et edition d articles dans l interface admin.',
            'active_menu' => 'article_editor',
            'cards' => $this->fetchCards($entityManager),
        ]);
    }

    #[Route('/admin/article-editor/cards', name: 'admin_article_editor_cards', methods: ['GET'])]
    public function listCards(EntityManagerInterface $entityManager): JsonResponse
    {
        return new JsonResponse(['cards' => $this->fetchCards($entityManager)]);
    }

    #[Route('/admin/article-editor/cards/{id}', name: 'admin_article_editor_card_show', methods: ['GET'])]
    public function showCard(AppCard $card, EntityManagerInterface $entityManager): JsonResponse
    {
        $response = [
            'card' => $this->serializeCard($card),
            'article' => null,
            'modules' => [],
        ];

        if (in_array($card->getType(), self::ARTICLE_TYPES, true)) {
            $content = $entityManager->getRepository(AppArticleContent::class)->findOneBy(['card' => $card]);
            $response['article'] = [
                'content_json' => $content instanceof AppArticleContent ? $content->getBodyJson() : null,
            ];

            return new JsonResponse($response);
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
            $textItem = $itemRepo->findOneBy([
                'module' => $module,
                'itemType' => 'text',
            ]);
            $response['modules'][] = [
                'id' => $module->getId(),
                'title' => $module->getTitle(),
                'order_index' => $module->getOrderIndex(),
                'content_json' => $textItem instanceof AppCycleModuleItem ? $textItem->getContentJson() : null,
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/article-editor/card', name: 'admin_article_editor_card', methods: ['POST'])]
    public function createCard(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $type = is_string($payload['type'] ?? null) ? trim((string) $payload['type']) : 'article';
        if (!in_array($type, self::CARD_TYPES, true)) {
            return $this->jsonError('Type de card invalide.', 400);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return $this->jsonError('Titre obligatoire.', 400);
        }

        $baseSlug = trim((string) ($payload['slug'] ?? ''));
        if ($baseSlug === '') {
            $baseSlug = $this->slugify($title);
        } else {
            $baseSlug = $this->slugify($baseSlug);
        }
        if ($baseSlug === '') {
            return $this->jsonError('Slug obligatoire.', 400);
        }

        $accessLevel = is_string($payload['access_level'] ?? null) ? trim((string) $payload['access_level']) : 'free';
        if (!in_array($accessLevel, self::ACCESS_LEVELS, true)) {
            return $this->jsonError('Access level invalide.', 400);
        }

        $featuredRank = $this->parseFeaturedRank($payload['featured_rank'] ?? null);
        if ($featuredRank === false) {
            return $this->jsonError('featured_rank invalide.', 400);
        }

        $card = new AppCard();
        $card->setType($type);
        $card->setTitle($title);
        // Commentaire: slug temporaire pour obtenir l ID avant de fixer le slug final.
        $card->setSlug($this->buildTempSlug($baseSlug));
        $card->setAccessLevel($accessLevel);
        $card->setFeaturedRank($featuredRank);
        $baseline = trim((string) ($payload['baseline'] ?? ''));
        if ($baseline !== '') {
            $card->setBaseline($baseline);
        }
        $description = $payload['description'] ?? null;
        if (is_string($description)) {
            $description = trim($description);
        } else {
            $description = null;
        }
        if ($description !== null && $description !== '') {
            $card->setDescription($description);
        }
        if (isset($payload['cover_media_id']) && is_numeric($payload['cover_media_id'])) {
            $media = $entityManager->getRepository(AppMedia::class)->find((int) $payload['cover_media_id']);
            if ($media instanceof AppMedia) {
                $card->setCoverMedia($media);
            }
        }

        $entityManager->persist($card);
        $entityManager->flush();
        $card->setSlug($this->buildSlugWithId((string) $card->getId(), $baseSlug));
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'card_id' => $card->getId(),
            'type' => $card->getType(),
            'slug' => $card->getSlug(),
            'title' => $card->getTitle(),
            'baseline' => $card->getBaseline(),
            'description' => $card->getDescription(),
            'access_level' => $card->getAccessLevel(),
            'featured_rank' => $card->getFeaturedRank(),
            'cover_media_id' => $card->getCoverMedia()?->getId(),
        ], 201);
    }

    #[Route('/admin/article-editor/card/{id}', name: 'admin_article_editor_card_update', methods: ['POST'])]
    public function updateCard(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
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
        $type = is_string($payload['type'] ?? null) ? trim((string) $payload['type']) : 'article';
        if (!in_array($type, self::CARD_TYPES, true)) {
            return $this->jsonError('Type de card invalide.', 400);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return $this->jsonError('Titre obligatoire.', 400);
        }

        $baseSlug = trim((string) ($payload['slug'] ?? ''));
        if ($baseSlug === '') {
            $baseSlug = $this->slugify($title);
        } else {
            $baseSlug = $this->slugify($baseSlug);
        }
        $baseSlug = $this->truncateSlug($baseSlug, self::SLUG_MAX_LENGTH);
        if ($baseSlug === '') {
            return $this->jsonError('Slug obligatoire.', 400);
        }

        $existing = $entityManager->getRepository(AppCard::class)->findOneBy(['slug' => $baseSlug]);
        if ($existing instanceof AppCard && $existing->getId() !== $card->getId()) {
            return $this->jsonError('Slug deja utilise.', 400);
        }

        $accessLevel = is_string($payload['access_level'] ?? null) ? trim((string) $payload['access_level']) : 'free';
        if (!in_array($accessLevel, self::ACCESS_LEVELS, true)) {
            return $this->jsonError('Access level invalide.', 400);
        }

        $featuredRank = $this->parseFeaturedRank($payload['featured_rank'] ?? null);
        if ($featuredRank === false) {
            return $this->jsonError('featured_rank invalide.', 400);
        }

        $card->setType($type);
        $card->setTitle($title);
        $card->setSlug($baseSlug);
        $card->setAccessLevel($accessLevel);
        $card->setFeaturedRank($featuredRank);

        $status = is_string($payload['status'] ?? null) ? trim((string) $payload['status']) : $card->getStatus();
        if (!in_array($status, ['draft', 'published', 'hidden', 'blocked', 'scheduled'], true)) {
            return $this->jsonError('Statut invalide.', 400);
        }
        if ($card->getStatus() !== $status) {
            $card->setStatus($status);
        }
        if ($status === 'published' && $card->getPublishedAt() === null) {
            $card->setPublishedAt(new \DateTimeImmutable());
        }

        $baseline = trim((string) ($payload['baseline'] ?? ''));
        $card->setBaseline($baseline !== '' ? $baseline : null);

        $description = $payload['description'] ?? null;
        if (is_string($description)) {
            $description = trim($description);
        } else {
            $description = null;
        }
        $card->setDescription($description !== '' ? $description : null);

        if (array_key_exists('cover_media_id', $payload)) {
            $coverMediaId = $payload['cover_media_id'];
            if ($coverMediaId === null || $coverMediaId === '') {
                $card->setCoverMedia(null);
            } elseif (is_numeric($coverMediaId)) {
                $media = $entityManager->getRepository(AppMedia::class)->find((int) $coverMediaId);
                if ($media instanceof AppMedia) {
                    $card->setCoverMedia($media);
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'card_id' => $card->getId(),
            'type' => $card->getType(),
            'slug' => $card->getSlug(),
            'title' => $card->getTitle(),
            'baseline' => $card->getBaseline(),
            'description' => $card->getDescription(),
            'access_level' => $card->getAccessLevel(),
            'featured_rank' => $card->getFeaturedRank(),
            'cover_media_id' => $card->getCoverMedia()?->getId(),
        ], 200);
    }

    #[Route('/admin/article-editor/card-meta', name: 'admin_article_editor_card_meta', methods: ['POST'])]
    public function updateCardMeta(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $cardId = $payload['card_id'] ?? null;
        if (!is_numeric($cardId)) {
            return $this->jsonError('card_id manquant.', 400);
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $cardId);
        if (!$card instanceof AppCard) {
            return $this->jsonError('Card introuvable.', 404);
        }

        $featuredRank = $this->parseFeaturedRank($payload['featured_rank'] ?? null);
        if ($featuredRank === false) {
            return $this->jsonError('featured_rank invalide.', 400);
        }

        $card->setFeaturedRank($featuredRank);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'card_id' => $card->getId(),
            'featured_rank' => $card->getFeaturedRank(),
        ]);
    }

    #[Route('/admin/article-editor/module', name: 'admin_article_editor_module', methods: ['POST'])]
    public function createModule(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $cycleCardId = $payload['cycle_card_id'] ?? null;
        if (!is_numeric($cycleCardId)) {
            return $this->jsonError('cycle_card_id manquant.', 400);
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $cycleCardId);
        if (!$card instanceof AppCard || $card->getType() !== 'cycle') {
            return $this->jsonError('Card cycle introuvable.', 404);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return $this->jsonError('Titre de module obligatoire.', 400);
        }

        $orderIndex = null;
        if (isset($payload['order_index']) && is_numeric($payload['order_index'])) {
            $orderIndex = (int) $payload['order_index'];
        }
        if ($orderIndex === null || $orderIndex < 1) {
            $orderIndex = $this->nextModuleOrderIndex($entityManager, $card);
        }

        $module = new AppCycleModule();
        $module->setCycleCard($card);
        $module->setTitle($title);
        $module->setOrderIndex($orderIndex);
        $baseline = trim((string) ($payload['baseline'] ?? ''));
        if ($baseline !== '') {
            $module->setBaseline($baseline);
        }

        $entityManager->persist($module);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'module_id' => $module->getId(),
            'order_index' => $module->getOrderIndex(),
            'title' => $module->getTitle(),
        ], 201);
    }

    #[Route('/admin/article-editor/content', name: 'admin_article_editor_content', methods: ['POST'])]
    public function saveContent(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $cardId = $payload['card_id'] ?? null;
        if (!is_numeric($cardId)) {
            return $this->jsonError('card_id manquant.', 400);
        }

        /** @var AppCard|null $card */
        $card = $entityManager->getRepository(AppCard::class)->find((int) $cardId);
        if (!$card instanceof AppCard) {
            return $this->jsonError('Card introuvable.', 404);
        }

        $contentJson = $payload['content_json'] ?? null;
        if (is_string($contentJson)) {
            $decoded = json_decode($contentJson, true);
            if (is_array($decoded)) {
                $contentJson = $decoded;
            }
        }
        if (!is_array($contentJson)) {
            $contentJson = [];
        }

        if (in_array($card->getType(), self::ARTICLE_TYPES, true)) {
            $contentRepo = $entityManager->getRepository(AppArticleContent::class);
            $content = $contentRepo->findOneBy(['card' => $card]);
            if (!$content instanceof AppArticleContent) {
                $content = new AppArticleContent($card);
            }
            $content->setBodyJson($contentJson);
            $entityManager->persist($content);
            $entityManager->flush();

            return new JsonResponse([
                'status' => 'ok',
                'card_id' => $card->getId(),
            ]);
        }

        $moduleId = $payload['module_id'] ?? null;
        if (!is_numeric($moduleId)) {
            return $this->jsonError('module_id manquant pour un cycle.', 400);
        }

        /** @var AppCycleModule|null $module */
        $module = $entityManager->getRepository(AppCycleModule::class)->find((int) $moduleId);
        if (!$module instanceof AppCycleModule || $module->getCycleCard()?->getId() !== $card->getId()) {
            return $this->jsonError('Module introuvable ou hors cycle.', 404);
        }

        if ($contentJson === []) {
            return $this->jsonError('content_json obligatoire pour item_type=text.', 400);
        }

        $itemRepo = $entityManager->getRepository(AppCycleModuleItem::class);
        $item = $itemRepo->findOneBy(['module' => $module, 'itemType' => 'text']);
        if (!$item instanceof AppCycleModuleItem) {
            $item = new AppCycleModuleItem();
            $item->setModule($module);
            $item->setItemType('text');
            $item->setOrderIndex($this->nextItemOrderIndex($entityManager, $module));
        }

        $item->setContentJson($contentJson);
        $entityManager->persist($item);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'card_id' => $card->getId(),
            'module_id' => $module->getId(),
            'item_id' => $item->getId(),
        ]);
    }

    #[Route('/admin/article-editor/media', name: 'admin_article_editor_media', methods: ['POST'])]
    public function uploadMedia(Request $request, EntityManagerInterface $entityManager, ImageProcessor $imageProcessor): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $file = $request->files->get('image');
        }
        if (!$file instanceof UploadedFile) {
            return $this->jsonError('Fichier manquant.', 400);
        }
        if (!$file->isValid()) {
            // Commentaire: on remonte une erreur claire quand l upload PHP echoue (taille, tmp, etc.).
            return $this->jsonError($this->formatUploadError($file->getError()), 400);
        }
        try {
            $media = $imageProcessor->processUpload($file);
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 415);
        } catch (\Throwable $exception) {
            return $this->jsonError('Erreur image: ' . $exception->getMessage(), 500);
        }

        $entityManager->persist($media);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'media_id' => $media->getId(),
            'app_url' => '/' . $media->getAppPath(),
            'img_mini_url' => $media->getImgMiniPath() ? '/' . $media->getImgMiniPath() : null,
            'img_tel_url' => $media->getImgTelPath() ? '/' . $media->getImgTelPath() : null,
            'img_tab_url' => $media->getImgTabPath() ? '/' . $media->getImgTabPath() : null,
        ], 201);
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

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'error' => $message,
        ], $status);
    }

    private function formatUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload trop volumineux (verifie upload_max_filesize/post_max_size).',
            UPLOAD_ERR_PARTIAL => 'Upload incomplet.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d ecrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Upload bloque par une extension PHP.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier recu.',
            default => 'Upload invalide.',
        };
    }

    private function serializeCard(AppCard $card): array
    {
        $cover = $card->getCoverMedia();
        $coverUrl = $cover instanceof AppMedia ? '/' . $cover->getAppPath() : null;

        return [
            'id' => $card->getId(),
            'type' => $card->getType(),
            'title' => $card->getTitle(),
            'slug' => $card->getSlug(),
            'baseline' => $card->getBaseline(),
            'description' => $card->getDescription(),
            'access_level' => $card->getAccessLevel(),
            'status' => $card->getStatus(),
            'featured_rank' => $card->getFeaturedRank(),
            'cover_media_id' => $cover instanceof AppMedia ? $cover->getId() : null,
            'cover_url' => $coverUrl,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCards(EntityManagerInterface $entityManager): array
    {
        $cards = $entityManager->createQueryBuilder()
            ->select('c', 'm')
            ->from(AppCard::class, 'c')
            ->leftJoin('c.coverMedia', 'm')
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $payload = [];
        foreach ($cards as $card) {
            if ($card instanceof AppCard) {
                $payload[] = $this->serializeCard($card);
            }
        }

        return $payload;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if (is_string($ascii)) {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value;
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

    private function buildTempSlug(string $baseSlug): string
    {
        $suffix = '-tmp-' . bin2hex(random_bytes(3));
        $maxBase = self::SLUG_MAX_LENGTH - strlen($suffix);
        $baseSlug = $this->truncateSlug($baseSlug, $maxBase);
        if ($baseSlug === '') {
            $baseSlug = 'card';
        }

        return $baseSlug . $suffix;
    }

    private function buildSlugWithId(string $id, string $baseSlug): string
    {
        $prefix = $id . '-';
        $maxBase = self::SLUG_MAX_LENGTH - strlen($prefix);
        if ($maxBase < 1) {
            return $id;
        }
        $baseSlug = $this->truncateSlug($baseSlug, $maxBase);
        if ($baseSlug === '') {
            return $id;
        }

        return $prefix . $baseSlug;
    }

    private function truncateSlug(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        $value = substr($value, 0, $maxLength);

        return rtrim($value, '-');
    }

    private function nextModuleOrderIndex(EntityManagerInterface $entityManager, AppCard $card): int
    {
        $qb = $entityManager->createQueryBuilder();
        $max = $qb
            ->select('MAX(m.orderIndex)')
            ->from(AppCycleModule::class, 'm')
            ->where('m.cycleCard = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    private function nextItemOrderIndex(EntityManagerInterface $entityManager, AppCycleModule $module): int
    {
        $qb = $entityManager->createQueryBuilder();
        $max = $qb
            ->select('MAX(i.orderIndex)')
            ->from(AppCycleModuleItem::class, 'i')
            ->where('i.module = :module')
            ->setParameter('module', $module)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

}
