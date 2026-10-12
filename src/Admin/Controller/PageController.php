<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\PageAdminType;
use App\Admin\Form\PageData;
use App\Admin\TranslationApplier;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Security\Permission;
use App\Version\VersionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création et modification d'une page : titre, texte et référencement dans chaque langue.
 */
final class PageController extends AbstractController
{
    private const array FIELD_NAMES = ['title', 'body', 'metaTitle', 'metaDescription'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationApplier $translations,
        private readonly VersionRecorder $versions,
    ) {
    }

    #[Route('/admin/pages/nouveau', name: 'app_admin_page_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_CREATE, Page::class);

        $data = new PageData();
        $data->translations = $this->translations->read(null, self::FIELD_NAMES);

        $form = $this->createForm(PageAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $page = new Page();
            $this->translations->apply($page, $data->translations, ['fr', 'en'], PageTranslation::class);
            $this->entityManager->persist($page);
            $this->entityManager->flush();
            $this->versions->record($page);
            $this->addFlash('success', 'admin.flash.created');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $page->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/page/form.html.twig', $this->context($form, null, $data, $request->getLocale()), $this->status($form));
    }

    #[Route('/admin/pages/{id}/proprietes', name: 'app_admin_page_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $page = $this->entityManager->find(Page::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(Permission::CONTENT_EDIT, $page);

        $data = new PageData();
        $data->translations = $this->translations->read($page, self::FIELD_NAMES);

        $form = $this->createForm(PageAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->translations->apply($page, $data->translations, ['fr', 'en'], PageTranslation::class);
            $this->entityManager->flush();
            $this->versions->record($page);
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_page_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/page/form.html.twig', $this->context($form, $page, $data, $request->getLocale()), $this->status($form));
    }

    /**
     * @param FormInterface<PageData> $form
     *
     * @return array<string, mixed>
     */
    private function context(FormInterface $form, ?Page $page, PageData $data, string $locale): array
    {
        return [
            'form' => $form,
            'page' => $page,
            'tabs' => $this->translations->completeness($data->translations, self::FIELD_NAMES),
            'active' => $locale,
        ];
    }

    /**
     * @param FormInterface<PageData> $form
     */
    private function status(FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
