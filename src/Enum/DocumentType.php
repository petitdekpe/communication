<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'un document officiel de la bibliothèque (filtre « type »).
 */
enum DocumentType: string
{
    case Law = 'law';
    case Decree = 'decree';
    case Order = 'order';
    case Report = 'report';
    case Strategy = 'strategy';
    case Form = 'form';

    public function labelKey(): string
    {
        return 'document_type.'.$this->value;
    }
}
