<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie éditoriale d'un contenu (actualité, document…).
 */
enum ContentCategory: string
{
    case Actualite = 'actualite';
    case Communique = 'communique';
    case Dossier = 'dossier';
    case Annonce = 'annonce';
    case AvisOfficiel = 'avis-officiel';

    public function labelKey(): string
    {
        return 'category.'.$this->value;
    }
}
