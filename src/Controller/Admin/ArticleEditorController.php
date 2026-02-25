<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur admin: affiche la page "Article editor" dans l interface.
 * Pourquoi: fournir un point d entree clair dans l admin, sans logique metier pour l instant.
 * Info: route GET /admin/article-editor, active_menu = article_editor.
 */
final class ArticleEditorController extends AbstractController
{
    #[Route('/admin/article-editor', name: 'admin_article_editor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/article_editor.html.twig', [
            'page_title' => 'Article editor',
            'page_subtitle' => 'Creation et edition d articles dans l interface admin.',
            'active_menu' => 'article_editor',
        ]);
    }
}
