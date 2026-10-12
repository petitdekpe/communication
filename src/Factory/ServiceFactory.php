<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Enum\Audience;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<Service>
 */
final class ServiceFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Service::class;
    }

    public function featured(): static
    {
        return $this->with(['featured' => true]);
    }

    protected function defaults(): array
    {
        return [
            'audience' => self::faker()->randomElement(Audience::cases()),
            'online' => self::faker()->boolean(),
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new ServiceTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Intitulé de la démarche', 'Procedure title', $number))
            ->setSummary(self::text($locale, 'Résumé de la démarche', 'Procedure summary'))
            ->setBody(self::text($locale, 'Étapes de la démarche', 'Procedure steps'))
            ->setCost(self::MARKER)
            ->setDelay(self::MARKER)
            ->setSteps([
                self::text($locale, 'Première étape', 'First step'),
                self::text($locale, 'Deuxième étape', 'Second step'),
                self::text($locale, 'Troisième étape', 'Third step'),
            ])
            ->setRequiredDocuments([
                self::text($locale, 'Première pièce requise', 'First required document'),
                self::text($locale, 'Deuxième pièce requise', 'Second required document'),
            ])
            ->setContact(self::text($locale, 'Coordonnées du service', 'Service contact details'));
    }
}
