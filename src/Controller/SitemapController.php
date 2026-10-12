<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cache\CacheTag;
use App\Cache\RequestTags;
use App\Page\SitemapBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan du site HTML (par langue) et sitemap.xml (toutes langues, avec les alternates hreflang), générés depuis
 * l'arborescence des pages publiées.
 */
final class SitemapController extends AbstractController
{
    #[Route(path: ['fr' => '/plan-du-site', 'en' => '/sitemap'], name: 'app_sitemap_html', methods: ['GET'], defaults: ['_cacheable' => true])]
    public function html(Request $request, SitemapBuilder $sitemap, RequestTags $tags): Response
    {
        $tags->add(CacheTag::PAGES_TREE);

        return $this->render('sitemap/index.html.twig', [
            'sections' => $sitemap->sections($request->getLocale()),
            'pages' => $sitemap->pages($request->getLocale()),
        ]);
    }

    /** Route « app_sitemap_xml » déclarée dans config/routes.yaml : hors préfixe de langue, l'adresse /sitemap.xml est celle que lisent les moteurs de recherche. */
    public function xml(SitemapBuilder $sitemap, RequestTags $tags): Response
    {
        $tags->add(CacheTag::PAGES_TREE);

        $response = $this->render('sitemap/sitemap.xml.twig', ['entries' => $sitemap->entries(), 'default_locale' => $sitemap->defaultLocale()]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
