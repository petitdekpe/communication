<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Avancement d'une demande déposée sur le portail (consultable sur la page de suivi).
 */
enum RequestStatus: string
{
    case Received = 'received';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelKey(): string
    {
        return 'tracking.status.'.$this->value;
    }
}
