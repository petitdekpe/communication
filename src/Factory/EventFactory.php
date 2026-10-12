<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Enum\EventType;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<Event>
 */
final class EventFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Event::class;
    }

    protected function defaults(): array
    {
        return [
            'type' => EventType::Other,
            'startsAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('+1 day', '+90 days')),
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new EventTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Intitulé de l\'événement', 'Event title', $number))
            ->setLocation(self::text($locale, 'Lieu', 'Venue'))
            ->setDescription(self::text($locale, 'Description de l\'événement', 'Event description'));
    }
}
