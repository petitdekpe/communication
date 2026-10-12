<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleType: string
{
    case Actualite = 'actualite';
    case Communique = 'communique';
    case Dossier = 'dossier';
    case AvisOfficiel = 'avis-officiel';

    /** Catégorie éditoriale affichée par le composant Badge. */
    public function category(): ContentCategory
    {
        return match ($this) {
            self::Actualite => ContentCategory::Actualite,
            self::Communique => ContentCategory::Communique,
            self::Dossier => ContentCategory::Dossier,
            self::AvisOfficiel => ContentCategory::AvisOfficiel,
        };
    }
}
