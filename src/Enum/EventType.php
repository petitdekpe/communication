<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'un événement de l'agenda (filtre « type »).
 */
enum EventType: string
{
    case PressBriefing = 'press_briefing';
    case Workshop = 'workshop';
    case Summit = 'summit';
    case Conference = 'conference';
    case Ceremony = 'ceremony';
    case Other = 'other';

    public function labelKey(): string
    {
        return 'event_type.'.$this->value;
    }
}
