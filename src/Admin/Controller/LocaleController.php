<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Security\AdminLocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bascule de la langue de travail (FR / EN) de l'administration, conservée en session.
 */
final class LocaleController extends AbstractController
{
    #[Route('/admin/langue', name: 'app_admin_locale', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_locale', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $locale = $request->request->getString('locale');
        if (\in_array($locale, AdminLocaleSubscriber::LOCALES, true)) {
            $request->getSession()->set(AdminLocaleSubscriber::SESSION_KEY, $locale);
        }

        // Redirection limitée aux pages de l'administration (jamais vers un site extérieur).
        $target = $request->request->getString('redirect');
        if (!str_starts_with($target, '/admin') || str_starts_with($target, '//') || str_contains($target, '\\')) {
            $target = $this->generateUrl('app_admin_dashboard');
        }

        return $this->redirect($target, 303);
    }
}
