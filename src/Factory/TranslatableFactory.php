<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Base des factories d'entités traduisibles : ajoute une traduction par langue demandée (fr et en par
 * défaut). Tous les textes générés sont des marqueurs « [À VALIDER] … X », jamais du contenu inventé.
 *
 * Attributs supplémentaires : locales (list<string>), number (int, numéro qui rend les textes uniques) et
 * texts (array<locale, array<champ, valeur>>, voir withTexts()).
 *
 * @template T of TranslatableInterface
 *
 * @extends PersistentObjectFactory<T>
 */
abstract class TranslatableFactory extends PersistentObjectFactory
{
    public const string MARKER = '[À VALIDER]';

    /**
     * Contenu non traduit en anglais : servi en français avec le bandeau de repli.
     */
    public function frenchOnly(): static
    {
        return $this->with(['locales' => ['fr']]);
    }

    /**
     * Impose des textes précis, par langue : ->withTexts(['fr' => ['title' => 'Décret n° 1', 'summary' => '…']]).
     * Les champs non cités gardent leur valeur par défaut (« [À VALIDER] … »).
     *
     * @param array<string, array<string, string>> $texts
     */
    public function withTexts(array $texts): static
    {
        return $this->with(['texts' => $texts]);
    }

    abstract protected function translation(string $locale, int $number): TranslationInterface;

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('locales', 'number', 'texts'))
            ->beforeInstantiate(static function (array $attributes): array {
                $attributes['locales'] ??= ['fr', 'en'];
                $attributes['number'] ??= Sequence::next();

                return $attributes;
            })
            ->afterInstantiate(function (TranslatableInterface $object, array $attributes): void {
                foreach ($attributes['locales'] as $locale) {
                    $translation = $this->translation($locale, $attributes['number']);
                    foreach (($attributes['texts'][$locale] ?? []) as $field => $value) {
                        $translation->{'set'.ucfirst($field)}($value);
                    }
                    $object->addTranslation($translation);
                }
            });
    }

    /**
     * Texte marqueur : « [À VALIDER] Titre de l'article 3 » / « [À VALIDER] Article title 3 ».
     */
    protected static function text(string $locale, string $french, string $english, ?int $number = null): string
    {
        $text = self::MARKER.' '.('fr' === $locale ? $french : $english);

        return null === $number ? $text : $text.' '.$number;
    }

    protected static function pastDate(string $from = '-90 days', string $to = '-1 day'): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween($from, $to));
    }
}
