<?php

declare(strict_types=1);

namespace App\Contact;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie du formulaire « Nous contacter » : nom et prénom, adresse électronique, téléphone, objet, message.
 */
final class ContactData
{
    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 150)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Email(message: 'request.error.email_invalid')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 40)]
    #[Assert\Regex(pattern: '/^[0-9+().\s-]*$/', message: 'request.error.phone_invalid')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $subject = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'contact.error.message_short', maxMessage: 'contact.error.message_long')]
    public ?string $message = null;

    #[Assert\IsTrue(message: 'request.error.consent_required')]
    public bool $consent = false;

    /** Piège à robots (voir App\Form\HoneypotType). */
    #[Assert\Blank(message: 'request.error.rejected')]
    public ?string $website = null;
}
