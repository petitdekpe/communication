<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionStatus: string
{
    /** Inscription demandée, lien de confirmation pas encore cliqué. */
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
}
