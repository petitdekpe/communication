<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue des Twig Components et de leurs variantes. Route absente en production
 * (déclarée pour dev et test uniquement : le test fonctionnel vérifie qu'elle s'affiche sans erreur).
 */
final class StyleguideController extends AbstractController
{
    #[Route('/_styleguide', name: 'app_styleguide', methods: ['GET'], env: ['dev', 'test'])]
    public function index(): Response
    {
        return $this->render('styleguide/index.html.twig');
    }
}
