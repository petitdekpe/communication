<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\HomePageType;
use App\Home\HomePageEditor;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Textes de la page d'accueil (ouverture, lettre d'information). Les blocs de contenus (actualités, agenda,
 * documents, chiffres clés…) se gèrent depuis leurs propres listes ; les modifications sont inscrites au journal d'audit.
 */
final class HomePageController extends AbstractController
{
    #[Route('/admin/accueil', name: 'app_admin_home', methods: ['GET', 'POST'])]
    public function edit(Request $request, HomePageEditor $editor): Response
    {
        $this->denyAccessUnlessGranted(Permission::HOME_MANAGE);

        $form = $this->createForm(HomePageType::class, $editor->load());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $editor->store($form->getData());
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/home/form.html.twig', ['form' => $form, 'can_manage_settings' => $this->isGranted(Permission::SETTINGS_MANAGE)], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
