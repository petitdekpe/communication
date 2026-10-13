<?php

declare(strict_types=1);

namespace App\Home;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lecture et enregistrement des textes de l'accueil modifiables depuis l'administration. Chaque champ est
 * enregistré tel quel par langue, sans repli sur le français (le formulaire affiche ce qui est stocké).
 * L'avis officiel reste géré dans les paramètres du site.
 */
final class HomePageEditor
{
    /** Champ du formulaire => [nom du paramètre, langue]. */
    private const array FIELDS = [
        'heroTitleFr' => [HomeSettings::HERO_TITLE, 'fr'],
        'heroTitleEn' => [HomeSettings::HERO_TITLE, 'en'],
        'heroLeadFr' => [HomeSettings::HERO_LEAD, 'fr'],
        'heroLeadEn' => [HomeSettings::HERO_LEAD, 'en'],
        'newsletterTextFr' => [HomeSettings::NEWSLETTER_TEXT, 'fr'],
        'newsletterTextEn' => [HomeSettings::NEWSLETTER_TEXT, 'en'],
        'newsletterConsentFr' => [HomeSettings::NEWSLETTER_CONSENT, 'fr'],
        'newsletterConsentEn' => [HomeSettings::NEWSLETTER_CONSENT, 'en'],
    ];

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function load(): HomePageData
    {
        $settings = $this->settings();
        $data = new HomePageData();
        foreach (self::FIELDS as $field => [$name, $locale]) {
            $translation = ($settings[$name] ?? null)?->getTranslation($locale);
            $value = $translation instanceof SettingTranslation ? trim((string) $translation->getValue()) : '';
            $data->{$field} = '' === $value ? null : $value;
        }

        return $data;
    }

    public function store(HomePageData $data): void
    {
        $settings = $this->settings();
        foreach (self::FIELDS as $field => [$name, $locale]) {
            $setting = $settings[$name] ?? null;
            if (null === $setting) {
                $setting = (new Setting())->setName($name);
                $this->entityManager->persist($setting);
                $settings[$name] = $setting;
            }

            $translation = $setting->getTranslation($locale);
            if (!$translation instanceof SettingTranslation) {
                $translation = new SettingTranslation();
                $translation->setLocale($locale);
                $setting->addTranslation($translation);
            }

            $value = trim((string) $data->{$field});
            $translation->setValue('' === $value ? null : $value);
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<string, Setting>
     */
    private function settings(): array
    {
        return $this->repository->findByNames(array_values(array_unique(array_column(self::FIELDS, 0))));
    }
}
