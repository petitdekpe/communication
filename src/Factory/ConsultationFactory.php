<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Consultation;
use App\Entity\ConsultationTranslation;
use App\Entity\Contract\TranslationInterface;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<Consultation>
 */
final class ConsultationFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Consultation::class;
    }

    protected function defaults(): array
    {
        return [
            'opensAt' => self::pastDate('-30 days', '-1 day'),
            'closesAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('+7 days', '+60 days')),
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate('-30 days', '-1 day'),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new ConsultationTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Intitulé de la consultation', 'Consultation title', $number))
            ->setSummary(self::text($locale, 'Présentation de la consultation', 'Consultation summary'))
            ->setBody(self::text($locale, 'Modalités de participation', 'How to take part'))
            ->setResults(self::text($locale, 'Résultats de la consultation', 'Consultation results'));
    }
}
