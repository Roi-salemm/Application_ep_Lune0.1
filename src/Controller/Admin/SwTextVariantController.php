<?php

namespace App\Controller\Admin;

use App\Entity\SwTextVariant;
use App\Form\Admin\SwTextVariantType;
use App\Repository\SwTextVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur admin de la table sw_text_variant.
 * Pourquoi: lister, creer, modifier et supprimer les variantes texte depuis une UI unique.
 */
final class SwTextVariantController extends AbstractController
{
    #[Route('/admin/sw-text-variant', name: 'admin_sw_text_variant', methods: ['GET'])]
    public function index(SwTextVariantRepository $repository): Response
    {
        $variants = $repository->findAllForAdmin();

        $createForm = $this->createForm(SwTextVariantType::class, new SwTextVariant(), [
            'action' => $this->generateUrl('admin_sw_text_variant_create'),
            'method' => 'POST',
        ]);

        return $this->render('admin/sw_text_variant.html.twig', [
            'active_menu' => 'sw_text_variant',
            'page_title' => 'SWTextVariant',
            'page_subtitle' => 'CRUD des variantes texte (family=symbolic, reading_mode=SYM_Weather).',
            'variants' => $variants,
            'create_form' => $createForm->createView(),
        ]);
    }

    #[Route('/admin/sw-text-variant/create', name: 'admin_sw_text_variant_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SwTextVariantRepository $repository
    ): RedirectResponse {
        $variant = new SwTextVariant();
        $form = $this->createForm(SwTextVariantType::class, $variant);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Soumission create invalide.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        if (!$form->isValid()) {
            $this->flashFormErrors($form);
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $variant->setFamily('symbolic');
        $variant->setReadingMode('SYM_Weather');
        if ($this->applySourceVariantFromForm($form, $variant, $repository) === false) {
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $entityManager->persist($variant);
        $entityManager->flush();

        $this->addFlash('success', 'SwTextVariant cree.');

        return $this->redirectToRoute('admin_sw_text_variant');
    }

    #[Route('/admin/sw-text-variant/{id}/update', name: 'admin_sw_text_variant_update', methods: ['POST'])]
    public function update(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SwTextVariantRepository $repository
    ): RedirectResponse {
        if (!ctype_digit($id)) {
            $this->addFlash('error', 'ID invalide.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $variant = $repository->find($id);
        if (!$variant instanceof SwTextVariant) {
            $this->addFlash('error', 'Ligne introuvable.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $form = $this->createForm(SwTextVariantType::class, $variant);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Soumission update invalide.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        if (!$form->isValid()) {
            $this->flashFormErrors($form);
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $variant->setFamily('symbolic');
        $variant->setReadingMode('SYM_Weather');
        if ($this->applySourceVariantFromForm($form, $variant, $repository) === false) {
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $entityManager->flush();
        $this->addFlash('success', 'SwTextVariant modifie.');

        return $this->redirectToRoute('admin_sw_text_variant');
    }

    #[Route('/admin/sw-text-variant/{id}/delete', name: 'admin_sw_text_variant_delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SwTextVariantRepository $repository
    ): RedirectResponse {
        if (!ctype_digit($id)) {
            $this->addFlash('error', 'ID invalide.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_sw_text_variant_' . $id, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $variant = $repository->find($id);
        if (!$variant instanceof SwTextVariant) {
            $this->addFlash('error', 'Ligne introuvable.');
            return $this->redirectToRoute('admin_sw_text_variant');
        }

        $entityManager->remove($variant);
        $entityManager->flush();

        $this->addFlash('success', 'SwTextVariant supprime.');

        return $this->redirectToRoute('admin_sw_text_variant');
    }

    /**
     * Mise a jour groupée des switches is_validated/is_used en un seul flush.
     * Pourquoi: eviter un aller-retour serveur par clic et laisser l'admin cocher librement.
     */
    #[Route('/admin/sw-text-variant/flags/bulk-update', name: 'admin_sw_text_variant_bulk_update_flags', methods: ['POST'])]
    public function bulkUpdateFlags(
        Request $request,
        EntityManagerInterface $entityManager,
        SwTextVariantRepository $repository
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('bulk_toggle_sw_text_variant', $token)) {
            return new JsonResponse(['success' => false, 'message' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $items = $payload['items'] ?? [];
        if (!is_array($items)) {
            return new JsonResponse(['success' => false, 'message' => 'Liste items invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $updated = 0;
        $invalidIds = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            if (!ctype_digit($id)) {
                $invalidIds[] = $id;
                continue;
            }

            $variant = $repository->find($id);
            if (!$variant instanceof SwTextVariant) {
                $invalidIds[] = $id;
                continue;
            }

            $variant->setIsValidated($this->boolFromMixed($item['is_validated'] ?? false));
            $variant->setIsUsed($this->boolFromMixed($item['is_used'] ?? false));
            $updated++;
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'updated' => $updated,
            'invalid_ids' => $invalidIds,
        ]);
    }

    private function applySourceVariantFromForm(
        FormInterface $form,
        SwTextVariant $variant,
        SwTextVariantRepository $repository
    ): bool {
        $sourceId = $form->get('source_variant_id')->getData();
        if ($sourceId === null || $sourceId === '') {
            $variant->setSourceVariant(null);
            return true;
        }

        $sourceIdString = (string) (int) $sourceId;
        if ($variant->getId() !== null && $sourceIdString === $variant->getId()) {
            $this->addFlash('error', 'source_variant_id ne peut pas pointer vers la meme ligne.');
            return false;
        }

        $sourceVariant = $repository->find($sourceIdString);
        if (!$sourceVariant instanceof SwTextVariant) {
            $this->addFlash('error', 'source_variant_id introuvable.');
            return false;
        }

        $variant->setSourceVariant($sourceVariant);

        return true;
    }

    private function flashFormErrors(FormInterface $form): void
    {
        foreach ($form->getErrors(true, true) as $error) {
            $message = trim($error->getMessage());
            if ($message !== '') {
                $this->addFlash('error', $message);
            }
        }
    }

    private function boolFromMixed(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true);
    }
}
