<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cache\CacheTag;
use App\Cache\RequestTags;
use App\Home\HomeSections;
use App\Home\HomeSettings;
use App\Newsletter\NewsletterSubscription;
use App\Newsletter\NewsletterSubscriptionType;
use App\Settings\SiteSettings;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accueil : contenus publiés (à la une, communiqués, actualités, documents, agenda, démarches, chiffres clés,
 * consultation) et textes d'ouverture lus dans les paramètres du site.
 */
final class HomeController extends AbstractController
{
    #[Route('', name: 'app_home', methods: ['GET'], defaults: ['_cacheable' => true])]
    public function index(Request $request, HomeSections $content, HomeSettings $settings, SiteSettings $site, ClockInterface $clock, RequestTags $tags): Response
    {
        $tags->add(CacheTag::CONTENT);
        $tags->add(CacheTag::SETTINGS);

        $agendaEnabled = $site->agendaEnabled();

        return $this->render('home/index.html.twig', [
            'locale' => $request->getLocale(),
            'now' => $clock->now(),
            'settings' => $settings,
            'featured' => $content->featured(),
            'releases' => $content->releases(),
            'news' => $content->news(),
            'documents' => $content->documents(),
            'events' => $agendaEnabled ? $content->events() : [],
            'agenda_enabled' => $agendaEnabled,
            'services' => $content->services(),
            'figures' => $content->figures(),
            'consultation' => $content->consultation(),
            'newsletterForm' => $this->createForm(NewsletterSubscriptionType::class, new NewsletterSubscription(), ['action' => $this->generateUrl('app_newsletter')])->createView(),
        ]);
    }
}
