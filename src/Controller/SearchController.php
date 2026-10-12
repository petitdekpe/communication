<?php

declare(strict_types=1);

namespace App\Controller;

use App\Search\SearchService;
use App\Search\SearchType;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche globale (modale Ctrl/Cmd+K, champ de l'accueil, URL /recherche?q=…) : résultats groupés par type.
 */
final class SearchController extends AbstractController
{
    #[Route(path: ['fr' => '/recherche', 'en' => '/search'], name: 'app_search', methods: ['GET'])]
    public function index(Request $request, SearchService $search, ClockInterface $clock): Response
    {
        $raw = $request->query->get('q');
        $query = SearchService::normalize(\is_string($raw) ? $raw : '');

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'tooShort' => '' !== $query && mb_strlen($query) < SearchService::MIN_LENGTH,
            'groups' => $search->search($query, $request->getLocale(), $clock->now()),
            'types' => SearchType::cases(),
        ]);
    }
}
