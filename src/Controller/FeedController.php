<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FeedController extends AbstractController
{
    private const int ITEMS = 30;

    #[Route(path: ['fr' => '/actualites.rss', 'en' => '/news.rss'], name: 'app_news_feed', methods: ['GET'])]
    public function __invoke(ArticleRepository $articles, ClockInterface $clock): Response
    {
        $response = $this->render('news/feed.xml.twig', [
            'articles' => $articles->findForFeed(self::ITEMS, $clock->now()),
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
