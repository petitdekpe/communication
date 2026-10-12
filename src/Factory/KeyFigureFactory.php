<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\KeyFigure;
use App\Entity\KeyFigureTranslation;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<KeyFigure>
 */
final class KeyFigureFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return KeyFigure::class;
    }

    protected function defaults(): array
    {
        return [
            'value' => null,
            'dataDate' => self::pastDate('-1 year'),
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new KeyFigureTranslation())
            ->setLocale($locale)
            ->setLabel(self::text($locale, 'Libellé du chiffre clé', 'Key figure label', $number))
            ->setSource(self::text($locale, 'Source', 'Source'));
    }
}
