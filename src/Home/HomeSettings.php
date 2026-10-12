<?php

declare(strict_types=1);

namespace App\Home;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Repository\SettingRepository;

/**
 * Textes éditoriaux de l'accueil enregistrés dans la table des paramètres (avis officiel, ouverture, lettre
 * d'information). Une valeur absente dans la langue de la page est cherchée en français ; une valeur vide
 * est considérée comme absente (le bloc correspondant n'est pas affiché).
 */
final class HomeSettings
{
    public const string NOTICE_MESSAGE = 'home.notice.message';
    public const string NOTICE_LINK = 'home.notice.href';
    public const string NOTICE_STARTS = 'home.notice.starts_at';
    public const string NOTICE_ENDS = 'home.notice.ends_at';
    public const string HERO_TITLE = 'home.hero.title';
    public const string HERO_LEAD = 'home.hero.lead';
    public const string HERO_IMAGE = 'home.hero.image';
    public const string HERO_IMAGE_ALT = 'home.hero.image_alt';
    public const string NEWSLETTER_TEXT = 'home.newsletter.text';
    public const string NEWSLETTER_CONSENT = 'newsletter.consent';

    private const array NAMES = [
        self::NOTICE_MESSAGE, self::NOTICE_LINK, self::NOTICE_STARTS, self::NOTICE_ENDS, self::HERO_TITLE, self::HERO_LEAD, self::HERO_IMAGE,
        self::HERO_IMAGE_ALT, self::NEWSLETTER_TEXT, self::NEWSLETTER_CONSENT,
    ];

    /** @var array<string, Setting>|null */
    private ?array $settings = null;

    public function __construct(private readonly SettingRepository $repository)
    {
    }

    public function get(string $name, string $locale): ?string
    {
        $setting = $this->settings()[$name] ?? null;
        if (null === $setting) {
            return null;
        }

        $translation = $setting->getTranslation($locale) ?? $setting->getTranslation('fr');
        $value = $translation instanceof SettingTranslation ? trim((string) $translation->getValue()) : '';

        return '' === $value ? null : $value;
    }

    /** Vrai si l'avis est dans sa fenêtre d'affichage (dates facultatives : sans date, il s'affiche dès qu'il a un texte). */
    public function noticeIsActive(\DateTimeImmutable $now): bool
    {
        $starts = $this->moment(self::NOTICE_STARTS);
        $ends = $this->moment(self::NOTICE_ENDS);

        return (null === $starts || $now >= $starts) && (null === $ends || $now < $ends);
    }

    private function moment(string $name): ?\DateTimeImmutable
    {
        $value = $this->get($name, 'fr');
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** Date de dernière modification : identifie une version de l'avis (un avis modifié réapparaît chez ceux qui l'ont masqué). */
    public function updatedAt(string $name): ?\DateTimeImmutable
    {
        return ($this->settings()[$name] ?? null)?->getUpdatedAt();
    }

    /**
     * @return array<string, Setting>
     */
    private function settings(): array
    {
        return $this->settings ??= $this->repository->findByNames(self::NAMES);
    }
}
