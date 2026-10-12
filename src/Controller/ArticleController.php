<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Routing\AlternateUrlResolver;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ArticleController extends AbstractController
{
    private const int RELATED = 3;

    #[Route(
        path: ['fr' => '/actualites/{slug}', 'en' => '/news/{slug}'],
        name: 'app_article_show',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET'],
    )]
    public function show(string $slug, Request $request, ArticleRepository $articles, ClockInterface $clock): Response
    {
        $locale = $request->getLocale();
        $now = $clock->now();
        $article = $articles->findPublishedBySlug($slug, $locale, $now)
            ?? throw $this->createNotFoundException();

        // Slug français demandé sous /en alors qu'une traduction existe : redirection vers l'URL canonique.
        $translation = $article->translate($locale);
        if ($translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_article_show', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $article);

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'translation' => $translation,
            'related' => $articles->findRelated($article, self::RELATED, $now),
            'attachments' => $article->getPublishedAttachments($now),
        ]);
    }
}
