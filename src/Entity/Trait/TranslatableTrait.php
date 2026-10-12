<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use Doctrine\Common\Collections\ReadableCollection;

/**
 * Implémentation (lecture et repli) de TranslatableInterface. L'entité déclare elle-même :
 *  - la relation, typée précisément :
 *      #[ORM\OneToMany(targetEntity: ArticleTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
 *      private Collection $translations;   // Collection<string, ArticleTranslation>, initialisée dans le constructeur
 *  - addTranslation() / removeTranslation(), qui vérifient le type de traduction (instanceof) puis
 *    délèguent la règle « une traduction par langue » à canAttachTranslation().
 */
trait TranslatableTrait
{
    /**
     * @return ReadableCollection<string, TranslationInterface>
     */
    public function getTranslations(): ReadableCollection
    {
        return $this->translations;
    }

    public function getTranslation(string $locale): ?TranslationInterface
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function hasTranslation(string $locale): bool
    {
        return null !== $this->getTranslation($locale);
    }

    public function translate(string $locale): TranslationInterface
    {
        $translation = $this->getTranslation($locale)
            ?? $this->getTranslation(TranslatableInterface::DEFAULT_LOCALE)
            ?? $this->translations->first();

        if (!$translation instanceof TranslationInterface) {
            throw new \LogicException(\sprintf('%s n\'a aucune traduction.', static::class));
        }

        return $translation;
    }

    public function isTranslationFallback(string $locale): bool
    {
        return !$this->hasTranslation($locale);
    }

    /**
     * @return list<string>
     */
    public function getAvailableLocales(): array
    {
        return array_values(array_map(static fn (TranslationInterface $t): string => $t->getLocale(), $this->translations->toArray()));
    }

    /**
     * Vrai si la traduction peut être ajoutée (aucune traduction dans sa langue), faux si c'est déjà
     * elle ; exception si une autre traduction occupe la langue.
     */
    protected function canAttachTranslation(TranslationInterface $translation): bool
    {
        $existing = $this->getTranslation($translation->getLocale());
        if (null !== $existing && $existing !== $translation) {
            throw new \LogicException(\sprintf('Une traduction "%s" existe déjà pour %s.', $translation->getLocale(), static::class));
        }

        return null === $existing;
    }
}
