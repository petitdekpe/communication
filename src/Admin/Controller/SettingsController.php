<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\SiteSettingsType;
use App\Security\Permission;
use App\Settings\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres du site : coordonnées, réseaux sociaux, avis officiel, suivi d'audience, durées de conservation.
 * Réservé au super administrateur ; les modifications sont inscrites au journal d'audit.
 */
final class SettingsController extends AbstractController
{
    #[Route('/admin/parametres', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request, SiteSettings $settings): Response
    {
        $this->denyAccessUnlessGranted(Permission::SETTINGS_MANAGE);

        $form = $this->createForm(SiteSettingsType::class, $settings->load());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $settings->store($form->getData());
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_settings', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/settings/form.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
