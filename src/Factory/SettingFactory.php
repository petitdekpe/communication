<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Setting;
use App\Entity\SettingTranslation;
use Zenstruck\Foundry\Object\Instantiator;

/**
 * Attribut supplémentaire « values » (langue => valeur) : valeurs précises à la place du texte marqueur
 * (par exemple le chemin technique d'une image).
 *
 * @extends TranslatableFactory<Setting>
 */
final class SettingFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Setting::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => 'setting.'.self::faker()->unique()->word(),
        ];
    }

    protected function initialize(): static
    {
        return parent::initialize()
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('locales', 'number', 'values'))
            ->afterInstantiate(static function (Setting $setting, array $attributes): void {
                foreach ((array) ($attributes['values'] ?? []) as $locale => $value) {
                    $translation = $setting->getTranslation((string) $locale);
                    if ($translation instanceof SettingTranslation) {
                        $translation->setValue((string) $value);
                    }
                }
            });
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new SettingTranslation())
            ->setLocale($locale)
            ->setValue(self::text($locale, 'Valeur du paramètre', 'Setting value'));
    }
}
