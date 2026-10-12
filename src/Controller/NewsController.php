<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ArticleType;
use App\News\NewsListingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route(path: ['fr' => '/actualites', 'en' => '/news'], name: 'app_news_index', methods: ['GET'])]
    public function index(Request $request, NewsListingService $listings): Response
    {
        $listing = $listings->listing($request->query->all(), $request->getLocale());
        if ($listing->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('news/index.html.twig', ['listing' => $listing, 'types' => ArticleType::cases()]);
    }

    #[Route(path: ['fr' => '/communiques', 'en' => '/press-releases'], name: 'app_press_releases', methods: ['GET'])]
    public function pressReleases(Request $request, NewsListingService $listings): Response
    {
        $listing = $listings->listing($request->query->all(), $request->getLocale(), ArticleType::Communique);
        if ($listing->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('news/press_releases.html.twig', ['listing' => $listing]);
    }
}
