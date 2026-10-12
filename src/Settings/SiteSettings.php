<?php

declare(strict_types=1);

namespace App\Settings;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Home\HomeSettings;
use App\Repository\SettingRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Paramètres du site (coordonnées, réseaux sociaux, avis officiel, suivi d'audience, durées de conservation),
 * stockés dans la table des paramètres. Seul l'avis officiel est traduit ; les autres valeurs sont enregistrées
 * dans la langue de référence (français) et servies telles quelles dans toutes les langues.
 * Une valeur vide est considérée comme absente.
 */
final class SiteSettings
{
    public const string PENDING = '[À VALIDER]';

    public const string CONTACT_ADDRESS = 'contact.address';
    public const string CONTACT_PHONE = 'contact.phone';
    public const string CONTACT_EMAIL = 'contact.email';
    public const string SUPPORT_PHONE = 'support.phone';
    public const string SOCIAL_FACEBOOK = 'social.facebook';
    public const string SOCIAL_X = 'social.x';
    public const string SOCIAL_YOUTUBE = 'social.youtube';
    public const string SOCIAL_LINKEDIN = 'social.linkedin';
    public const string ANALYTICS = 'analytics.code';
    public const string RETENTION_REQUESTS = 'retention.requests_months';
    public const string RETENTION_CONTRIBUTIONS = 'retention.contributions_months';
    public const string RETENTION_SUBSCRIBERS = 'retention.subscribers_months';

    private const array NAMES = [
        self::CONTACT_ADDRESS, self::CONTACT_PHONE, self::CONTACT_EMAIL, self::SUPPORT_PHONE,
        self::SOCIAL_FACEBOOK, self::SOCIAL_X, self::SOCIAL_YOUTUBE, self::SOCIAL_LINKEDIN,
        HomeSettings::NOTICE_MESSAGE, HomeSettings::NOTICE_LINK, HomeSettings::NOTICE_STARTS, HomeSettings::NOTICE_ENDS,
        self::ANALYTICS, self::RETENTION_REQUESTS, self::RETENTION_CONTRIBUTIONS, self::RETENTION_SUBSCRIBERS,
    ];

    /** @var array<string, Setting>|null */
    private ?array $settings = null;

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Valeur dans la langue demandée, sinon en français ; null si absente ou vide. */
    public function get(string $name, string $locale = 'fr'): ?string
    {
        $setting = $this->settings()[$name] ?? null;
        $translation = $setting?->getTranslation($locale) ?? $setting?->getTranslation('fr');
        $value = $translation instanceof SettingTranslation ? trim((string) $translation->getValue()) : '';

        return '' === $value ? null : $value;
    }

    /** Valeur à afficher : la valeur saisie, ou le marqueur [À VALIDER] tant qu'elle n'est pas renseignée. */
    public function orPending(string $name, string $locale = 'fr'): string
    {
        return $this->get($name, $locale) ?? self::PENDING;
    }

    /** Entier strictement positif, ou null (durée de conservation non paramétrée : aucune purge). */
    public function getPositiveInt(string $name): ?int
    {
        $value = $this->get($name);

        return null !== $value && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function load(): SiteSettingsData
    {
        $data = new SiteSettingsData();
        $data->address = $this->get(self::CONTACT_ADDRESS);
        $data->phone = $this->get(self::CONTACT_PHONE);
        $data->email = $this->get(self::CONTACT_EMAIL);
        $data->supportPhone = $this->get(self::SUPPORT_PHONE);
        $data->facebook = $this->get(self::SOCIAL_FACEBOOK);
        $data->x = $this->get(self::SOCIAL_X);
        $data->youtube = $this->get(self::SOCIAL_YOUTUBE);
        $data->linkedin = $this->get(self::SOCIAL_LINKEDIN);
        $data->noticeFr = $this->own(HomeSettings::NOTICE_MESSAGE, 'fr');
        $data->noticeEn = $this->own(HomeSettings::NOTICE_MESSAGE, 'en');
        $data->noticeLink = $this->get(HomeSettings::NOTICE_LINK);
        $data->noticeStartsAt = $this->date(HomeSettings::NOTICE_STARTS);
        $data->noticeEndsAt = $this->date(HomeSettings::NOTICE_ENDS);
        $data->analyticsCode = $this->get(self::ANALYTICS);
        $data->retentionRequests = $this->getPositiveInt(self::RETENTION_REQUESTS);
        $data->retentionContributions = $this->getPositiveInt(self::RETENTION_CONTRIBUTIONS);
        $data->retentionSubscribers = $this->getPositiveInt(self::RETENTION_SUBSCRIBERS);

        return $data;
    }

    public function store(SiteSettingsData $data): void
    {
        $utc = new \DateTimeZone('UTC');
        $values = [
            self::CONTACT_ADDRESS => ['fr' => $data->address],
            self::CONTACT_PHONE => ['fr' => $data->phone],
            self::CONTACT_EMAIL => ['fr' => $data->email],
            self::SUPPORT_PHONE => ['fr' => $data->supportPhone],
            self::SOCIAL_FACEBOOK => ['fr' => $data->facebook],
            self::SOCIAL_X => ['fr' => $data->x],
            self::SOCIAL_YOUTUBE => ['fr' => $data->youtube],
            self::SOCIAL_LINKEDIN => ['fr' => $data->linkedin],
            HomeSettings::NOTICE_MESSAGE => ['fr' => $data->noticeFr, 'en' => $data->noticeEn],
            HomeSettings::NOTICE_LINK => ['fr' => $data->noticeLink],
            HomeSettings::NOTICE_STARTS => ['fr' => $data->noticeStartsAt?->setTimezone($utc)->format(\DATE_ATOM)],
            HomeSettings::NOTICE_ENDS => ['fr' => $data->noticeEndsAt?->setTimezone($utc)->format(\DATE_ATOM)],
            self::ANALYTICS => ['fr' => $data->analyticsCode],
            self::RETENTION_REQUESTS => ['fr' => null === $data->retentionRequests ? null : (string) $data->retentionRequests],
            self::RETENTION_CONTRIBUTIONS => ['fr' => null === $data->retentionContributions ? null : (string) $data->retentionContributions],
            self::RETENTION_SUBSCRIBERS => ['fr' => null === $data->retentionSubscribers ? null : (string) $data->retentionSubscribers],
        ];

        foreach ($values as $name => $byLocale) {
            $setting = $this->settings()[$name] ?? null;
            if (null === $setting) {
                $setting = (new Setting())->setName($name);
                $this->entityManager->persist($setting);
                $this->settings = [...$this->settings(), $name => $setting];
            }
            foreach ($byLocale as $locale => $value) {
                $translation = $setting->getTranslation($locale);
                if (!$translation instanceof SettingTranslation) {
                    $translation = new SettingTranslation();
                    $translation->setLocale($locale);
                    $setting->addTranslation($translation);
                }
                $value = null === $value ? null : trim($value);
                $translation->setValue('' === $value ? null : $value);
            }
        }

        $this->entityManager->flush();
    }

    /** Valeur propre à la langue, sans repli sur le français (chaque champ du formulaire s'affiche tel qu'enregistré). */
    private function own(string $name, string $locale): ?string
    {
        $translation = ($this->settings()[$name] ?? null)?->getTranslation($locale);
        $value = $translation instanceof SettingTranslation ? trim((string) $translation->getValue()) : '';

        return '' === $value ? null : $value;
    }

    private function date(string $name): ?\DateTimeImmutable
    {
        $value = $this->get($name);
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, Setting>
     */
    private function settings(): array
    {
        try {
            return $this->settings ??= $this->repository->findByNames(self::NAMES);
        } catch (DbalException) {
            // Base indisponible : les pages publiques s'affichent avec les valeurs par défaut ([À VALIDER]).
            return [];
        }
    }
}
