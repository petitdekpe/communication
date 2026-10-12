<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Lit et écrit les champs traduits d'un contenu à partir des tableaux du formulaire (langue => champ => valeur),
 * et calcule la complétude de chaque langue (champs attendus renseignés).
 */
final class TranslationApplier
{
    private readonly PropertyAccessorInterface $accessor;

    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, array<string, string|null>>
     */
    public function read(?TranslatableInterface $entity, array $fields): array
    {
        $data = [];
        foreach (['fr', 'en'] as $locale) {
            $translation = $entity?->getTranslation($locale);
            foreach ($fields as $field) {
                $value = null === $translation ? null : $this->accessor->getValue($translation, $field);
                $data[$locale][$field] = \is_string($value) ? $value : null;
            }
        }

        return $data;
    }

    /**
     * Écrit les langues autorisées. Une langue entièrement vide et sans traduction existante n'est pas créée.
     *
     * @param array<string, array<string, string|null>> $data
     * @param list<string>                              $locales langues modifiables par l'utilisateur
     * @param class-string<TranslationInterface>        $class
     */
    public function apply(TranslatableInterface $entity, array $data, array $locales, string $class): void
    {
        foreach ($locales as $locale) {
            $values = $data[$locale] ?? [];
            $translation = $entity->getTranslation($locale);
            if (null === $translation) {
                if ([] === array_filter($values, static fn (?string $v): bool => null !== $v && '' !== trim($v))) {
                    continue;
                }
                $translation = (new $class())->setLocale($locale);
                $entity->addTranslation($translation);
            }
            foreach ($values as $field => $value) {
                $value = null === $value ? null : trim($value);
                // Les champs obligatoires de la base (titre) ne reçoivent jamais null.
                $this->accessor->setValue($translation, $field, '' === $value || null === $value ? ('title' === $field || 'label' === $field ? (string) $this->accessor->getValue($translation, $field) : null) : $value);
            }
        }
    }

    /**
     * @param array<string, array<string, string|null>> $data
     * @param list<string>                              $expected champs comptés pour la complétude
     *
     * @return list<array{locale: string, complete: int, total: int}>
     */
    public function completeness(array $data, array $expected): array
    {
        $tabs = [];
        foreach (['fr', 'en'] as $locale) {
            $filled = 0;
            foreach ($expected as $field) {
                if ('' !== trim((string) ($data[$locale][$field] ?? ''))) {
                    ++$filled;
                }
            }
            $tabs[] = ['locale' => $locale, 'complete' => $filled, 'total' => \count($expected)];
        }

        return $tabs;
    }
}
