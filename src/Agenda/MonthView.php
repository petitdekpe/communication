<?php

declare(strict_types=1);

namespace App\Agenda;

use App\Entity\Event;
use App\Enum\EventType;

/**
 * Vue mois de l'agenda : semaines complètes (lundi au dimanche) autour du mois demandé.
 * Chaque jour est exprimé dans le fuseau d'affichage (Africa/Porto-Novo).
 */
final readonly class MonthView
{
    /**
     * @param \DateTimeImmutable                                                                           $first  premier jour du mois, 00:00 dans le fuseau d'affichage
     * @param list<list<array{date: \DateTimeImmutable, inMonth: bool, today: bool, events: list<Event>}>> $weeks
     * @param list<Event>                                                                                  $events événements qui commencent dans le mois, du plus tôt au plus tard
     */
    public function __construct(
        public \DateTimeImmutable $first,
        public array $weeks,
        public array $events,
        public ?EventType $type,
        public string $previous,
        public string $next,
    ) {
    }

    /** Mois courant au format « 2026-10 » (paramètre d'URL). */
    public function key(): string
    {
        return $this->first->format('Y-m');
    }
}
