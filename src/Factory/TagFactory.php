<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Tag;
use App\Entity\TagTranslation;

/**
 * @extends TranslatableFactory<Tag>
 */
final class TagFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Tag::class;
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new TagTranslation())
            ->setLocale($locale)
            ->setName(self::text($locale, 'Mot-clé', 'Keyword', $number));
    }
}
