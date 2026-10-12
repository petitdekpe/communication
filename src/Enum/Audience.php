<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Public visé par une démarche ou un contenu.
 */
enum Audience: string
{
    case Journalistes = 'journalistes';
    case Medias = 'medias';
    case Entreprises = 'entreprises';
    case Citoyens = 'citoyens';

    public function labelKey(): string
    {
        return 'audience.'.$this->value;
    }
}
