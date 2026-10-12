<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Contract\TranslationInterface;

/**
 * @extends TranslatableFactory<Category>
 */
final class CategoryFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Category::class;
    }

    protected function defaults(): array
    {
        return [
            'position' => 0,
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new CategoryTranslation())
            ->setLocale($locale)
            ->setName(self::text($locale, 'Rubrique', 'Section', $number))
            ->setDescription(self::text($locale, 'Description de la rubrique', 'Section description'));
    }
}
