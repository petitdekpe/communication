<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\DashboardData;
use App\Entity\Article;
use App\Entity\OfficialDocument;
use App\Enum\ArticleType;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord : ce qui demande une action de l'utilisateur, selon son rôle, et des raccourcis de création.
 */
final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(Request $request, DashboardData $data): Response
    {
        $locale = $request->getLocale();

        $shortcuts = [];
        if ($this->isGranted(Permission::CONTENT_CREATE, Article::class)) {
            $shortcuts[] = ['label' => 'admin.shortcut.press_release', 'icon' => 'ri:megaphone-line', 'url' => $this->generateUrl('app_admin_article_new', ['type' => ArticleType::Communique->value])];
            $shortcuts[] = ['label' => 'admin.shortcut.news', 'icon' => 'ri:newspaper-line', 'url' => $this->generateUrl('app_admin_article_new', ['type' => ArticleType::Actualite->value])];
        }
        if ($this->isGranted(Permission::CONTENT_CREATE, OfficialDocument::class)) {
            $shortcuts[] = ['label' => 'admin.shortcut.document', 'icon' => 'ri:file-add-line', 'url' => $this->generateUrl('app_admin_document_new')];
        }

        return $this->render('admin/dashboard.html.twig', [
            'shortcuts' => $shortcuts,
            'toReview' => $this->isGranted('ROLE_REVIEWER') ? $data->toReview($locale) : null,
            'requests' => $this->isGranted(Permission::REQUEST_VIEW) ? $data->pendingRequests($locale) : null,
            'translations' => $this->isGranted('ROLE_TRANSLATOR') || $this->isGranted('ROLE_EDITOR') ? $data->missingTranslations($locale) : null,
            'publications' => $data->latestPublications(),
        ]);
    }
}
