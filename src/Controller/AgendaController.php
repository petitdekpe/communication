<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agenda\AgendaService;
use App\Agenda\IcalendarBuilder;
use App\Enum\EventType;
use App\Repository\EventRepository;
use App\Routing\AlternateUrlResolver;
use App\Settings\SiteSettings;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AgendaController extends AbstractController
{
    private const string CALENDAR_TYPE = 'text/calendar; charset=utf-8';

    public function __construct(private readonly SiteSettings $settings)
    {
    }

    #[Route(path: ['fr' => '/agenda', 'en' => '/events'], name: 'app_agenda', methods: ['GET'])]
    public function index(Request $request, AgendaService $agenda): Response
    {
        $this->denyUnlessEnabled();
        $query = $request->query->all();
        $view = 'month' === ($query['view'] ?? null) ? 'month' : 'list';

        if ('month' === $view) {
            return $this->render('agenda/index.html.twig', ['view' => $view, 'month' => $agenda->month($query), 'types' => EventType::cases()]);
        }

        $listing = $agenda->listing($query);
        if ($listing->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('agenda/index.html.twig', ['view' => $view, 'listing' => $listing, 'types' => EventType::cases()]);
    }

    /** Flux iCal global : un abonnement suffit pour suivre tous les événements publiés. */
    #[Route(path: ['fr' => '/agenda.ics', 'en' => '/events.ics'], name: 'app_agenda_feed', methods: ['GET'])]
    public function feed(Request $request, EventRepository $events, IcalendarBuilder $builder, ClockInterface $clock, TranslatorInterface $translator): Response
    {
        $this->denyUnlessEnabled();
        $response = new Response(
            $builder->build($events->findForFeed($clock->now()), $request->getLocale(), $translator->trans('agenda.title')),
            Response::HTTP_OK,
            ['Content-Type' => self::CALENDAR_TYPE],
        );
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }

    #[Route(
        path: ['fr' => '/agenda/{slug}', 'en' => '/events/{slug}'],
        name: 'app_event_show',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET'],
    )]
    public function show(string $slug, Request $request, EventRepository $events, ClockInterface $clock): Response
    {
        $this->denyUnlessEnabled();
        $locale = $request->getLocale();
        $event = $events->findPublishedBySlug($slug, $locale, $clock->now()) ?? throw $this->createNotFoundException();

        // Slug français demandé sous /en alors qu'une traduction existe : redirection vers l'URL canonique.
        $translation = $event->translate($locale);
        if ($translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_event_show', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $event);

        return $this->render('agenda/show.html.twig', ['event' => $event, 'translation' => $translation]);
    }

    /** Export .ics d'un événement, à ajouter à son calendrier. */
    #[Route(
        path: ['fr' => '/agenda/{slug}.ics', 'en' => '/events/{slug}.ics'],
        name: 'app_event_ics',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET'],
    )]
    public function ics(string $slug, Request $request, EventRepository $events, IcalendarBuilder $builder, ClockInterface $clock, TranslatorInterface $translator): Response
    {
        $this->denyUnlessEnabled();
        $locale = $request->getLocale();
        $event = $events->findPublishedBySlug($slug, $locale, $clock->now()) ?? throw $this->createNotFoundException();
        $translation = $event->translate($locale);

        $response = new Response(
            $builder->build([$event], $locale, $translation->getTitle() ?? $translator->trans('agenda.title')),
            Response::HTTP_OK,
            ['Content-Type' => self::CALENDAR_TYPE],
        );
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, ($translation->getSlug() ?? 'evenement').'.ics'));
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }

    /** Agenda désactivé dans les paramètres du site : les pages n'existent pas. */
    private function denyUnlessEnabled(): void
    {
        if (!$this->settings->agendaEnabled()) {
            throw $this->createNotFoundException();
        }
    }
}
