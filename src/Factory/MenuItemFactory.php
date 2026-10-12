<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\MenuItem;
use App\Entity\MenuItemTranslation;

/**
 * @extends TranslatableFactory<MenuItem>
 */
final class MenuItemFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return MenuItem::class;
    }

    protected function defaults(): array
    {
        return [
            'menu' => 'main',
            'position' => 0,
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new MenuItemTranslation())
            ->setLocale($locale)
            ->setLabel(self::text($locale, 'Entrée de menu', 'Menu item', $number));
    }
}
