<?php

declare(strict_types=1);

namespace App\Newsletter;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie du formulaire d'inscription : une adresse électronique, plus le piège à robots.
 */
final class NewsletterSubscription
{
    #[Assert\NotBlank(message: 'request.error.email_required', normalizer: 'trim')]
    #[Assert\Email(message: 'request.error.email_invalid')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Blank(message: 'request.error.rejected')]
    public ?string $website = null;
}
