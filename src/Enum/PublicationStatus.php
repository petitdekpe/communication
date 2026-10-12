<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de publication d'un contenu (le workflow Symfony s'appuiera sur ces états).
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    public function labelKey(): string
    {
        return 'publication_status.'.$this->value;
    }
}
