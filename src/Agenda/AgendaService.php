<?php

declare(strict_types=1);

namespace App\Agenda;

use App\Entity\Event;
use App\Enum\EventType;
use App\Repository\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Construit les vues de l'agenda (liste paginée, grille mensuelle) à partir des paramètres d'URL
 * (type, when, month, page). Les valeurs invalides sont ignorées : une URL partagée ne produit jamais d'erreur.
 */
final class AgendaService
{
    public const int PER_PAGE = 10;

    private readonly \DateTimeZone $siteZone;

    public function __construct(
        private readonly EventRepository $events,
        private readonly ClockInterface $clock,
        #[Autowire('%app.timezone%')]
        string $timezone,
    ) {
        $this->siteZone = new \DateTimeZone($timezone);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listing(array $query): AgendaListing
    {
        $type = EventType::tryFrom($this->string($query, 'type'));
        $upcoming = 'past' !== $this->string($query, 'when');
        $page = max(1, (int) $this->string($query, 'page'));

        $paginator = $this->events->paginate($upcoming, $type, $page, self::PER_PAGE, $this->clock->now());
        $total = \count($paginator);

        /** @var list<Event> $events */
        $events = iterator_to_array($paginator, false);

        return new AgendaListing(
            events: $events,
            upcoming: $upcoming,
            type: $type,
            page: $page,
            totalPages: (int) ceil($total / self::PER_PAGE),
            total: $total,
            params: array_filter([
                'type' => $type?->value,
                'when' => $upcoming ? null : 'past',
            ], static fn (mixed $value): bool => null !== $value),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function month(array $query): MonthView
    {
        $now = $this->clock->now();
        $type = EventType::tryFrom($this->string($query, 'type'));
        $first = $this->parseMonth($this->string($query, 'month'))
            ?? new \DateTimeImmutable('first day of this month 00:00:00', $this->siteZone);
        $first = $first->setTimezone($this->siteZone);
        $next = $first->modify('+1 month');

        // Grille : du lundi qui précède (ou est) le 1er au dimanche qui suit (ou est) le dernier jour du mois.
        $gridStart = $first->modify('monday this week');
        $gridEnd = $next->modify('-1 day')->modify('sunday this week')->modify('+1 day'); // exclu

        // La base stocke en UTC (fuseau PHP par défaut) : les bornes sont converties avant la requête.
        $storageZone = new \DateTimeZone(date_default_timezone_get());
        $events = $this->events->findBetween($gridStart->setTimezone($storageZone), $gridEnd->setTimezone($storageZone), $type, $now);

        $today = $now->setTimezone($this->siteZone)->format('Y-m-d');
        $byDay = $this->groupByDay($events);

        $weeks = [];
        for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')) {
            $weeks[intdiv((int) $day->diff($gridStart)->days, 7)][] = [
                'date' => $day,
                'inMonth' => $day->format('Y-m') === $first->format('Y-m'),
                'today' => $day->format('Y-m-d') === $today,
                'events' => $byDay[$day->format('Y-m-d')] ?? [],
            ];
        }

        $startingInMonth = array_values(array_filter(
            $events,
            fn (Event $event): bool => $this->day($event->getStartsAt()) >= $first->format('Y-m-d') && $this->day($event->getStartsAt()) < $next->format('Y-m-d'),
        ));

        return new MonthView(
            first: $first,
            weeks: array_values($weeks),
            events: $startingInMonth,
            type: $type,
            previous: $first->modify('-1 month')->format('Y-m'),
            next: $next->format('Y-m'),
        );
    }

    /**
     * Un événement de plusieurs jours apparaît chaque jour, du début à la fin.
     *
     * @param list<Event> $events
     *
     * @return array<string, list<Event>>
     */
    private function groupByDay(array $events): array
    {
        $byDay = [];
        foreach ($events as $event) {
            $startsAt = $event->getStartsAt();
            $endsAt = $event->getEndsAt();
            $start = $this->day($startsAt);
            $end = $this->day(null !== $endsAt && null !== $startsAt && $endsAt > $startsAt ? $endsAt : $startsAt);
            for ($day = new \DateTimeImmutable($start, $this->siteZone); $day->format('Y-m-d') <= $end; $day = $day->modify('+1 day')) {
                $byDay[$day->format('Y-m-d')][] = $event;
            }
        }

        return $byDay;
    }

    /** Jour calendaire (Y-m-d) de l'instant, dans le fuseau d'affichage. */
    private function day(?\DateTimeImmutable $instant): string
    {
        return ($instant ?? $this->clock->now())->setTimezone($this->siteZone)->format('Y-m-d');
    }

    private function parseMonth(string $value): ?\DateTimeImmutable
    {
        if (1 !== preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches) || (int) $matches[1] < 1970 || (int) $matches[1] > 2100) {
            return null;
        }

        return new \DateTimeImmutable($value.'-01 00:00:00', $this->siteZone);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function string(array $query, string $key): string
    {
        $value = $query[$key] ?? '';

        return \is_string($value) ? trim($value) : '';
    }
}
