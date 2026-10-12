<?php

declare(strict_types=1);

namespace App\Agenda;

use App\Entity\Event;
use App\Enum\EventType;

/**
 * Vue liste de l'agenda : une page d'événements à venir ou passés.
 */
final readonly class AgendaListing
{
    /**
     * @param list<Event>          $events
     * @param array<string, mixed> $params filtres actifs sous forme de paramètres d'URL (partageables)
     */
    public function __construct(
        public array $events,
        public bool $upcoming,
        public ?EventType $type,
        public int $page,
        public int $totalPages,
        public int $total,
        public array $params,
    ) {
    }

    /** Page demandée au-delà de la dernière (la page 1 d'une liste vide est valide). */
    public function isOutOfRange(): bool
    {
        return $this->page > max(1, $this->totalPages);
    }
}
