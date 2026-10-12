<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Audience;
use App\Repository\ServiceRepository;
use App\Routing\AlternateUrlResolver;
use App\ServiceRequest\ServiceFormRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ServiceController extends AbstractController
{
    /**
     * Liste des démarches, filtrable par public (?public=journalistes). Une valeur inconnue est ignorée.
     */
    #[Route(path: ['fr' => '/demarches', 'en' => '/services'], name: 'app_services_index', methods: ['GET'])]
    public function index(Request $request, ServiceRepository $services, ClockInterface $clock): Response
    {
        $filter = $request->query->get('public');
        $audience = \is_string($filter) ? Audience::tryFrom($filter) : null;

        return $this->render('service/index.html.twig', [
            'services' => $services->findPublished($clock->now(), $audience),
            'audiences' => Audience::cases(),
            'audience' => $audience,
        ]);
    }

    #[Route(
        path: ['fr' => '/demarches/{slug}', 'en' => '/services/{slug}'],
        name: 'app_service_show',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET'],
    )]
    public function show(string $slug, Request $request, ServiceRepository $services, ServiceFormRegistry $forms, ClockInterface $clock): Response
    {
        $locale = $request->getLocale();
        $service = $services->findPublishedBySlug($slug, $locale, $clock->now())
            ?? throw $this->createNotFoundException();

        // Slug français demandé sous /en alors qu'une traduction existe : redirection vers l'URL canonique.
        $translation = $service->translate($locale);
        if ($translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_service_show', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $service);

        return $this->render('service/show.html.twig', [
            'service' => $service,
            'translation' => $translation,
            // Le bouton « Faire la demande en ligne » n'apparaît que si le formulaire existe vraiment.
            'internalForm' => $service->hasInternalForm() && null !== $forms->get($service->getFormKey()),
        ]);
    }
}
