<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cache\CacheTag;
use App\Cache\RequestTags;
use App\Page\PageTree;
use App\Routing\AlternateUrlResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Page éditoriale, résolue par son chemin dans l'arborescence (« /fr/le-ministere/missions »).
 *
 * La route « app_page_show » (/{_locale}/{path}) est déclarée en dernier dans config/routes.yaml : toute route
 * explicite l'emporte. Un chemin qui ne désigne aucune page publiée répond 404 ; un chemin non canonique
 * (slash final, slugs français sur /en alors qu'une traduction existe) est redirigé vers l'adresse canonique.
 */
final class PageController extends AbstractController
{
    public function show(string $path, Request $request, PageTree $tree, RequestTags $tags, TranslatorInterface $translator): Response
    {
        $locale = $request->getLocale();
        $page = $tree->resolve($path, $locale) ?? throw $this->createNotFoundException();

        $canonical = $tree->path($page, $locale);
        if ($canonical !== $path) {
            return $this->redirect($tree->url($page, $locale), Response::HTTP_MOVED_PERMANENTLY);
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $page);
        $request->attributes->set('_page', $page);
        $tags->add(CacheTag::page((int) $page->getId()), CacheTag::PAGES_TREE);

        return $this->render('page/show.html.twig', [
            'page' => $page,
            'translation' => $page->translate($locale),
            'breadcrumb' => $tree->breadcrumb($page, $locale, $translator->trans('breadcrumb.home')),
        ]);
    }
}
