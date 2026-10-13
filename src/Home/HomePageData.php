<?php

declare(strict_types=1);

namespace App\Home;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Valeurs du formulaire « Page d'accueil » : titre et chapô de l'ouverture, texte et mention de la lettre
 * d'information, chacun en français et en anglais. Un champ vide revient au texte par défaut du site.
 */
final class HomePageData
{
    #[Assert\Length(max: 160)]
    public ?string $heroTitleFr = null;

    #[Assert\Length(max: 160)]
    public ?string $heroTitleEn = null;

    #[Assert\Length(max: 400)]
    public ?string $heroLeadFr = null;

    #[Assert\Length(max: 400)]
    public ?string $heroLeadEn = null;

    #[Assert\Length(max: 300)]
    public ?string $newsletterTextFr = null;

    #[Assert\Length(max: 300)]
    public ?string $newsletterTextEn = null;

    #[Assert\Length(max: 400)]
    public ?string $newsletterConsentFr = null;

    #[Assert\Length(max: 400)]
    public ?string $newsletterConsentEn = null;
}
