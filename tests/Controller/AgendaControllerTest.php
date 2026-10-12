<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Enum\EventType;
use App\Enum\PublicationStatus;
use App\Factory\EventFactory;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Agenda : liste (à venir / passés), vue mois, filtre par type, page d'un événement, export .ics
 * par événement et flux iCal global (relus avec un analyseur iCalendar, sabre/vobject).
 *
 * Les dates stockées sont en UTC ; l'affichage se fait à l'heure du Bénin (UTC+1).
 */
final class AgendaControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    // ---- Liste ---------------------------------------------------------------------

    public function testListShowsUpcomingEventsSoonestFirstAndHidesPastOnes(): void
    {
        $client = static::createClient();
        $later = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+20 days')]);
        $sooner = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+3 days')]);
        EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-3 days')]);

        $crawler = $client->request('GET', '/fr/agenda');

        self::assertResponseIsSuccessful();
        self::assertSame([$this->slug($sooner), $this->slug($later)], $this->eventSlugs($crawler));
        self::assertSame('2 événements', trim($crawler->filter('[role="status"]')->text()));
        self::assertSelectorExists('link[rel="alternate"][type="text/calendar"][href="/fr/agenda.ics"]');
        self::assertSelectorExists('a[href="/fr/agenda.ics"]');
    }

    public function testPastEventsAreListedMostRecentFirst(): void
    {
        $client = static::createClient();
        $older = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-30 days')]);
        $recent = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-2 days')]);
        EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+2 days')]);

        $crawler = $client->request('GET', '/fr/agenda', ['when' => 'past']);

        self::assertSame([$this->slug($recent), $this->slug($older)], $this->eventSlugs($crawler));
        self::assertSelectorExists('nav[aria-label="Période"] a[aria-current="page"]');
    }

    public function testAnEventInProgressIsStillUpcoming(): void
    {
        $client = static::createClient();
        $running = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-1 day'), 'endsAt' => new \DateTimeImmutable('+1 day')]);
        $finished = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-3 days'), 'endsAt' => new \DateTimeImmutable('-2 days')]);

        self::assertSame([$this->slug($running)], $this->eventSlugs($client->request('GET', '/fr/agenda')));
        self::assertSame([$this->slug($finished)], $this->eventSlugs($client->request('GET', '/fr/agenda', ['when' => 'past'])));
    }

    public function testFilterByType(): void
    {
        $client = static::createClient();
        $briefing = EventFactory::createOne(['type' => EventType::PressBriefing, 'startsAt' => new \DateTimeImmutable('+2 days')]);
        EventFactory::createOne(['type' => EventType::Workshop, 'startsAt' => new \DateTimeImmutable('+3 days')]);
        EventFactory::createOne(['type' => EventType::Summit, 'startsAt' => new \DateTimeImmutable('+4 days')]);

        $crawler = $client->request('GET', '/fr/agenda', ['type' => 'press_briefing']);

        self::assertSame([$this->slug($briefing)], $this->eventSlugs($crawler));
        self::assertSelectorExists('select[name="type"] option[value="press_briefing"][selected]');
        // Les options couvrent chaque type d'événement.
        self::assertCount(\count(EventType::cases()) + 1, $crawler->filter('select[name="type"] option'));
    }

    public function testOnlyPublishedEventsAreVisible(): void
    {
        $client = static::createClient();
        $visible = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+2 days')]);
        EventFactory::createOne(['status' => PublicationStatus::Draft, 'startsAt' => new \DateTimeImmutable('+3 days')]);
        EventFactory::createOne(['publishedAt' => new \DateTimeImmutable('+1 day'), 'startsAt' => new \DateTimeImmutable('+4 days')]);

        self::assertSame([$this->slug($visible)], $this->eventSlugs($client->request('GET', '/fr/agenda')));
    }

    public function testListIsPaginatedAndOutOfRangePageIsNotFound(): void
    {
        $client = static::createClient();
        foreach (range(1, 12) as $day) {
            EventFactory::createOne(['startsAt' => new \DateTimeImmutable(\sprintf('+%d days', $day))]);
        }

        self::assertCount(10, $this->eventSlugs($client->request('GET', '/fr/agenda')));
        self::assertCount(2, $this->eventSlugs($client->request('GET', '/fr/agenda', ['page' => 2])));
        $client->request('GET', '/fr/agenda', ['page' => 3]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyAgendaShowsAnEmptyState(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/agenda');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Aucun événement');
    }

    public function testInvalidParametersAreIgnored(): void
    {
        $client = static::createClient();
        EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+2 days')]);

        $crawler = $client->request('GET', '/fr/agenda', ['type' => 'inconnu', 'when' => 'jamais', 'view' => '<script>', 'page' => 'x']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->eventSlugs($crawler));

        foreach (['', 'abc', '2030-13', '1800-01', '2030-5', ['x']] as $month) {
            $client->request('GET', '/fr/agenda', ['view' => 'month', 'month' => $month]);
            self::assertResponseIsSuccessful();
        }
    }

    // ---- Dates et heures selon la langue --------------------------------------------

    public function testDatesAndTimesAreFormattedForTheLocaleInBeninTime(): void
    {
        $client = static::createClient();
        // 09:00 UTC = 10:00 à Porto-Novo ; vendredi 10 mai 2030.
        EventFactory::createOne([
            'type' => EventType::PressBriefing,
            'startsAt' => new \DateTimeImmutable('2030-05-10 09:00:00'),
            'endsAt' => new \DateTimeImmutable('2030-05-10 11:30:00'),
        ]);

        $fr = $client->request('GET', '/fr/agenda');
        self::assertSame('vendredi 10 mai 2030', $this->normalize($fr->filter('article time')->text()));
        self::assertSame('2030-05-10', $fr->filter('article time')->attr('datetime'));
        self::assertSame('10', $this->normalize($fr->filter('article [aria-hidden="true"] span')->eq(0)->text()));
        self::assertSame('mai', $this->normalize($fr->filter('article [aria-hidden="true"] span')->eq(1)->text()));
        self::assertStringContainsString('Point de presse · 10:00 – 12:30', $this->normalize($fr->filter('article')->text()));

        $en = $client->request('GET', '/en/events');
        self::assertSame('Friday, May 10, 2030', $this->normalize($en->filter('article time')->text()));
        self::assertSame('May', $this->normalize($en->filter('article [aria-hidden="true"] span')->eq(1)->text()));
        self::assertStringContainsString('Press briefing · 10:00 AM – 12:30 PM', $this->normalize($en->filter('article')->text()));
    }

    public function testDayIsTheBeninDayNotTheUtcDay(): void
    {
        $client = static::createClient();
        // 23:30 UTC le 10 mai = 00:30 le 11 mai à Porto-Novo.
        EventFactory::createOne(['startsAt' => new \DateTimeImmutable('2030-05-10 23:30:00')]);

        $crawler = $client->request('GET', '/fr/agenda');

        self::assertSame('samedi 11 mai 2030', $this->normalize($crawler->filter('article time')->text()));
        self::assertStringContainsString('00:30', $this->normalize($crawler->filter('article')->text()));
    }

    // ---- Vue mois --------------------------------------------------------------------

    public function testMonthViewIsAWeeklyGridWithEventsOnTheirDays(): void
    {
        $client = static::createClient();
        $briefing = EventFactory::createOne(['type' => EventType::PressBriefing, 'startsAt' => new \DateTimeImmutable('2030-05-10 09:00:00')]);
        $summit = EventFactory::createOne([
            'type' => EventType::Summit,
            'startsAt' => new \DateTimeImmutable('2030-05-14 08:00:00'),
            'endsAt' => new \DateTimeImmutable('2030-05-16 17:00:00'),
        ]);
        EventFactory::createOne(['startsAt' => new \DateTimeImmutable('2030-06-20 09:00:00')]); // un autre mois

        $crawler = $client->request('GET', '/fr/agenda', ['view' => 'month', 'month' => '2030-05']);

        self::assertResponseIsSuccessful();
        self::assertSame('Mai 2030', $this->normalize($crawler->filter('main h2')->first()->text()));
        $grid = $crawler->filter('table[data-agenda-grid]');
        self::assertSame('Agenda de Mai 2030', $this->normalize($grid->filter('caption')->text()));
        self::assertCount(7, $grid->filter('thead th[scope="col"]'));
        self::assertSame(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'], $grid->filter('thead th span[aria-hidden="true"]')->each(fn (Crawler $th): string => $this->normalize($th->text())));
        // Mai 2030 commence un mercredi et finit un vendredi : 5 semaines complètes, du 29 avril au 2 juin.
        self::assertCount(5, $grid->filter('tbody tr'));
        self::assertCount(35, $grid->filter('tbody td'));
        self::assertSame('2030-04-29', $grid->filter('tbody td time')->first()->attr('datetime'));
        self::assertSame('2030-06-02', $grid->filter('tbody td time')->last()->attr('datetime'));

        $briefingSlug = $this->slug($briefing);
        self::assertCount(1, $grid->filter(\sprintf('a[href="/fr/agenda/%s"]', $briefingSlug)));
        self::assertSame('2030-05-10', $grid->filter(\sprintf('a[href="/fr/agenda/%s"]', $briefingSlug))->ancestors()->filter('td')->first()->filter('time')->attr('datetime'));

        // Événement sur plusieurs jours : une occurrence par jour, du 14 au 16.
        $summitCells = $grid->filter(\sprintf('a[href="/fr/agenda/%s"]', $this->slug($summit)))->each(static fn (Crawler $a): string => (string) $a->ancestors()->filter('td')->first()->filter('time')->attr('datetime'));
        self::assertSame(['2030-05-14', '2030-05-15', '2030-05-16'], $summitCells);

        // Petits écrans : liste des événements qui commencent dans le mois (pas celui de juin).
        self::assertCount(2, $crawler->filter('main .md\:hidden article'));
    }

    public function testMonthViewNavigationKeepsTheTypeFilter(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/fr/agenda', ['view' => 'month', 'month' => '2030-01', 'type' => 'summit']);

        self::assertSame('/fr/agenda?type=summit&view=month&month=2029-12', $crawler->filter('a[rel="prev"]')->attr('href'));
        self::assertSame('/fr/agenda?type=summit&view=month&month=2030-02', $crawler->filter('a[rel="next"]')->attr('href'));
        self::assertSelectorExists('input[type="hidden"][name="view"][value="month"]');
        self::assertSelectorExists('input[type="hidden"][name="month"][value="2030-01"]');
    }

    public function testMonthViewFiltersByTypeAndPlacesLateEveningEventsOnTheBeninDay(): void
    {
        $client = static::createClient();
        $late = EventFactory::createOne(['type' => EventType::Ceremony, 'startsAt' => new \DateTimeImmutable('2030-05-31 23:30:00')]); // 1er juin à Porto-Novo
        EventFactory::createOne(['type' => EventType::Workshop, 'startsAt' => new \DateTimeImmutable('2030-05-20 09:00:00')]);

        $june = $client->request('GET', '/fr/agenda', ['view' => 'month', 'month' => '2030-06', 'type' => 'ceremony']);

        $cell = $june->filter(\sprintf('table a[href="/fr/agenda/%s"]', $this->slug($late)))->ancestors()->filter('td')->first();
        self::assertSame('2030-06-01', $cell->filter('time')->attr('datetime'));
        self::assertCount(1, $june->filter('table tbody a'), 'Le filtre masque l\'atelier');
    }

    public function testMonthViewMarksToday(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/fr/agenda', ['view' => 'month']);

        self::assertCount(1, $crawler->filter('table time[aria-current="date"]'));
    }

    // ---- Page d'un événement -----------------------------------------------------------

    public function testEventPage(): void
    {
        $client = static::createClient();
        $event = EventFactory::new()
            ->withTexts(['fr' => ['title' => 'Point de presse hebdomadaire', 'location' => 'Salle des conférences', 'description' => "Première ligne\nSeconde ligne <b>non HTML</b>"]])
            ->create(['type' => EventType::PressBriefing, 'startsAt' => new \DateTimeImmutable('2030-05-10 09:00:00'), 'endsAt' => new \DateTimeImmutable('2030-05-10 11:00:00')]);
        $slug = $this->slug($event);

        $crawler = $client->request('GET', '/fr/agenda/'.$slug);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Point de presse hebdomadaire');
        self::assertSelectorTextContains('main', 'Salle des conférences');
        self::assertSelectorTextContains('main', 'Point de presse');
        self::assertSame('2030-05-10T10:00:00+01:00', $crawler->filter('dl time')->first()->attr('datetime'));
        self::assertStringContainsString('vendredi 10 mai 2030', $this->normalize($crawler->filter('dl')->text()));
        self::assertStringContainsString('<br', $crawler->filter('article div.mt-8')->html());
        self::assertStringNotContainsString('<b>', $crawler->filter('article div.mt-8')->html(), 'Description échappée');
        self::assertSelectorExists(\sprintf('a[href="/fr/agenda/%s.ics"]', $slug));
        // Sélecteur de langue : la page équivalente en anglais.
        self::assertSelectorExists('a[hreflang="en"][href$="/en/events/'.$event->translate('en')->getSlug().'"]');
    }

    public function testEnglishPageRedirectsFrenchSlugAndShowsFallbackNoticeWhenNotTranslated(): void
    {
        $client = static::createClient();
        $translated = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+5 days')]);
        $frenchOnly = EventFactory::new()->frenchOnly()->create(['startsAt' => new \DateTimeImmutable('+6 days')]);

        $client->request('GET', '/en/events/'.$this->slug($translated));
        self::assertResponseRedirects('/en/events/'.$translated->translate('en')->getSlug(), 301);

        $client->request('GET', '/en/events/'.$this->slug($frenchOnly));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="note"]');
        self::assertSelectorExists('article[lang="fr"]');
    }

    public function testUnavailableEventIsNotFound(): void
    {
        $client = static::createClient();
        $draft = EventFactory::createOne(['status' => PublicationStatus::Draft]);

        $client->request('GET', '/fr/agenda/'.$this->slug($draft));
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/fr/agenda/'.$this->slug($draft).'.ics');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/fr/agenda/inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    // ---- Export iCalendar (.ics) ---------------------------------------------------------

    public function testSingleEventExportIsAValidICalendarFile(): void
    {
        $client = static::createClient();
        $event = EventFactory::new()
            ->withTexts(['fr' => [
                'title' => 'Point de presse, annonce; "résultats"',
                'location' => 'Salle A, Cotonou',
                'description' => "Ligne 1\nLigne 2 avec une virgule, un point-virgule; et une barre \\ inversée",
            ]])
            ->create(['type' => EventType::PressBriefing, 'startsAt' => new \DateTimeImmutable('2030-05-10 09:00:00'), 'endsAt' => new \DateTimeImmutable('2030-05-10 11:30:00')]);

        $client->request('GET', '/fr/agenda/'.$this->slug($event).'.ics');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename='.$this->slug($event).'.ics', $response->headers->get('Content-Disposition'));

        $calendar = $this->parse((string) $response->getContent());
        self::assertSame('2.0', (string) $calendar->VERSION);
        self::assertCount(1, $calendar->VEVENT);

        $vevent = $calendar->VEVENT;
        self::assertSame('event-'.$event->getId().'@localhost', (string) $vevent->UID);
        self::assertSame('Point de presse, annonce; "résultats"', (string) $vevent->SUMMARY);
        self::assertSame('Salle A, Cotonou', (string) $vevent->LOCATION);
        self::assertSame("Ligne 1\nLigne 2 avec une virgule, un point-virgule; et une barre \\ inversée", (string) $vevent->DESCRIPTION);
        self::assertSame('Point de presse', (string) $vevent->CATEGORIES);
        self::assertSame('http://localhost/fr/agenda/'.$this->slug($event), (string) $vevent->URL);
        self::assertSame('20300510T090000Z', (string) $vevent->DTSTART);
        self::assertSame('20300510T113000Z', (string) $vevent->DTEND);
        self::assertSame('2030-05-10T09:00:00+00:00', $vevent->DTSTART->getDateTime()->format('c'));
        self::assertNotEmpty((string) $vevent->DTSTAMP);
    }

    public function testExportOmitsAnEndThatIsNotAfterTheStart(): void
    {
        $client = static::createClient();
        $noEnd = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('2030-05-10 09:00:00')]);
        $inverted = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('2030-05-11 09:00:00'), 'endsAt' => new \DateTimeImmutable('2030-05-11 08:00:00')]);

        foreach ([$noEnd, $inverted] as $event) {
            $client->request('GET', '/fr/agenda/'.$this->slug($event).'.ics');
            $calendar = $this->parse((string) $client->getResponse()->getContent());
            self::assertNull($calendar->VEVENT->DTEND);
        }
    }

    public function testExportFoldsLongLinesWithoutBreakingMultibyteCharacters(): void
    {
        $client = static::createClient();
        $description = str_repeat('Le ministère présente la stratégie numérique à Cotonou, après-midi ; été. ', 12);
        $event = EventFactory::new()->withTexts(['fr' => ['description' => $description]])->create(['startsAt' => new \DateTimeImmutable('+5 days')]);

        $client->request('GET', '/fr/agenda/'.$this->slug($event).'.ics');

        $ics = (string) $client->getResponse()->getContent();
        self::assertStringEndsWith("\r\n", $ics);
        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), 'Ligne de plus de 75 octets : '.$line);
            self::assertTrue(mb_check_encoding($line, 'UTF-8'), 'Caractère multi-octets coupé');
        }
        self::assertSame($description, (string) $this->parse($ics)->VEVENT->DESCRIPTION);
    }

    public function testEnglishExportUsesEnglishTexts(): void
    {
        $client = static::createClient();
        $event = EventFactory::new()
            ->withTexts(['fr' => ['title' => 'Atelier des médias'], 'en' => ['title' => 'Media workshop']])
            ->create(['type' => EventType::Workshop, 'startsAt' => new \DateTimeImmutable('+5 days')]);

        $client->request('GET', '/en/events/'.$event->translate('en')->getSlug().'.ics');

        self::assertResponseIsSuccessful();
        $vevent = $this->parse((string) $client->getResponse()->getContent())->VEVENT;
        self::assertSame('Media workshop', (string) $vevent->SUMMARY);
        self::assertSame('Workshop', (string) $vevent->CATEGORIES);
        self::assertSame('http://localhost/en/events/'.$event->translate('en')->getSlug(), (string) $vevent->URL);
        // Même UID dans les deux langues : un client ne duplique pas l'événement.
        self::assertSame('event-'.$event->getId().'@localhost', (string) $vevent->UID);
    }

    public function testGlobalFeedContainsEveryPublishedEventAndNoOther(): void
    {
        $client = static::createClient();
        $past = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('-40 days')]);
        $upcoming = EventFactory::createOne(['startsAt' => new \DateTimeImmutable('+10 days')]);
        EventFactory::createOne(['status' => PublicationStatus::Draft, 'startsAt' => new \DateTimeImmutable('+11 days')]);
        EventFactory::createOne(['publishedAt' => new \DateTimeImmutable('+1 day'), 'startsAt' => new \DateTimeImmutable('+12 days')]);

        $client->request('GET', '/fr/agenda.ics');

        self::assertResponseIsSuccessful();
        self::assertSame('text/calendar; charset=utf-8', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertNull($client->getResponse()->headers->get('Content-Disposition'), 'Flux d\'abonnement : pas de téléchargement forcé');

        $calendar = $this->parse((string) $client->getResponse()->getContent());
        self::assertSame('Agenda', (string) $calendar->{'X-WR-CALNAME'});
        $uids = array_map(static fn ($vevent): string => (string) $vevent->UID, iterator_to_array($calendar->VEVENT, false));
        self::assertSame(['event-'.$past->getId().'@localhost', 'event-'.$upcoming->getId().'@localhost'], $uids, 'Du plus ancien au plus récent');
    }

    public function testEmptyFeedIsStillAValidCalendar(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/events.ics');

        self::assertResponseIsSuccessful();
        // RFC 5545 exige au moins un composant : seul avertissement attendu pour un calendrier encore vide,
        // que les clients d'agenda acceptent (le fichier reste syntaxiquement valide).
        $calendar = $this->parse((string) $client->getResponse()->getContent(), ['An iCalendar object must have at least 1 component.']);
        self::assertSame('Events', (string) $calendar->{'X-WR-CALNAME'});
        self::assertNull($calendar->VEVENT);
    }

    // ---- Outils ---------------------------------------------------------------------------

    /**
     * Relit le fichier avec un analyseur iCalendar strict : syntaxe invalide = exception, anomalie
     * du contenu (propriété obligatoire manquante, valeur incorrecte) = message de validation.
     * Les propriétés iCalendar (VEVENT, SUMMARY…) étant dynamiques, le calendrier est typé « object ».
     *
     * @param list<string> $expectedWarnings messages de validation attendus (aucun par défaut)
     */
    private function parse(string $ics, array $expectedWarnings = []): object
    {
        $calendar = Reader::read($ics);
        self::assertInstanceOf(VCalendar::class, $calendar);
        self::assertSame($expectedWarnings, array_map(static fn (array $warning): string => $warning['message'], $calendar->validate()), 'Calendrier invalide');

        return $calendar;
    }

    /**
     * @return list<string> slugs (français) des événements listés, dans l'ordre d'affichage
     */
    private function eventSlugs(Crawler $crawler): array
    {
        return array_map(
            static fn (string $href): string => (string) preg_replace('#^/fr/agenda/#', '', $href),
            $crawler->filter('main ol article h2 a')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
    }

    private function slug(Event $event): string
    {
        return (string) $event->translate('fr')->getSlug();
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }
}
