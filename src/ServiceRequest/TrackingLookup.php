<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie de la page de suivi : numéro de suivi + e-mail de la demande.
 */
final class TrackingLookup
{
    #[Assert\NotBlank(message: 'tracking.error.number_required', normalizer: 'trim')]
    #[Assert\Length(max: 40)]
    public ?string $number = null;

    #[Assert\NotBlank(message: 'request.error.email_required', normalizer: 'trim')]
    #[Assert\Email(message: 'request.error.email_invalid')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Blank(message: 'request.error.rejected')]
    public ?string $website = null;
}
