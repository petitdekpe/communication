<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cache\CacheTag;
use App\Cache\RequestTags;
use App\Contact\ContactData;
use App\Contact\ContactSubmitter;
use App\Contact\ContactType;
use App\Page\PageTree;
use App\Routing\AlternateUrlResolver;
use App\ServiceRequest\RequestRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * « Nous contacter » : coordonnées de la page éditoriale « contact » et formulaire d'envoi d'un message.
 * La route explicite l'emporte sur la route générique des pages (app_page_show).
 */
final class ContactController extends AbstractController
{
    #[Route(path: ['fr' => '/contact', 'en' => '/contact'], name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PageTree $tree,
        ContactSubmitter $submitter,
        RequestRateLimiter $limiter,
        RequestTags $tags,
        TranslatorInterface $translator,
    ): Response {
        $locale = $request->getLocale();
        $page = $tree->resolve('contact', $locale) ?? throw $this->createNotFoundException();

        $form = $this->createForm(ContactType::class, new ContactData());
        $form->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$limiter->consumeContact($request)->isAccepted()) {
                $form->addError(new FormError($translator->trans('request.error.too_many')));
            } elseif (!$form->isSubmitted()) {
                $form->addError(new FormError($translator->trans('request.error.too_large')));
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $submitter->submit($form->getData(), $locale);
                $this->addFlash('contact', 'contact.thanks');

                return $this->redirectToRoute('app_contact', [], Response::HTTP_SEE_OTHER);
            }
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $page);
        $tags->add(CacheTag::page((int) $page->getId()), CacheTag::PAGES_TREE);

        return $this->render('contact/index.html.twig', [
            'page' => $page,
            'translation' => $page->translate($locale),
            'breadcrumb' => $tree->breadcrumb($page, $locale, $translator->trans('breadcrumb.home')),
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
