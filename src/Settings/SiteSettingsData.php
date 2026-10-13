<?php

declare(strict_types=1);

namespace App\Settings;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Valeurs du formulaire « Paramètres du site ». Les champs vides sont acceptés : une valeur absente
 * n'est pas affichée sur le site (ou s'y affiche comme [À VALIDER] pour les coordonnées).
 */
final class SiteSettingsData
{
    #[Assert\Length(max: 255)]
    public ?string $address = null;

    #[Assert\Length(max: 50)]
    public ?string $phone = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 50)]
    public ?string $supportPhone = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $facebook = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $x = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $youtube = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $linkedin = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $ministerFacebook = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $ministerX = null;

    #[Assert\Url(message: 'admin.error.https_url', protocols: ['https'])]
    public ?string $ministerLinkedin = null;

    #[Assert\Length(max: 400)]
    public ?string $noticeFr = null;

    #[Assert\Length(max: 400)]
    public ?string $noticeEn = null;

    #[Assert\Regex(pattern: '~^(https://|/)~', message: 'admin.error.notice_link')]
    #[Assert\Length(max: 255)]
    public ?string $noticeLink = null;

    public ?\DateTimeImmutable $noticeStartsAt = null;

    #[Assert\Expression('this.noticeEndsAt === null or this.noticeStartsAt === null or this.noticeEndsAt > this.noticeStartsAt', message: 'admin.error.notice_dates')]
    public ?\DateTimeImmutable $noticeEndsAt = null;

    #[Assert\Length(max: 5000)]
    public ?string $analyticsCode = null;

    public bool $agendaEnabled = false;

    #[Assert\Range(min: 1, max: 240)]
    public ?int $retentionRequests = null;

    #[Assert\Range(min: 1, max: 240)]
    public ?int $retentionContributions = null;

    #[Assert\Range(min: 1, max: 240)]
    public ?int $retentionSubscribers = null;
}
