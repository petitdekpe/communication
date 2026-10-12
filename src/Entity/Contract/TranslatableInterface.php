<?php

declare(strict_types=1);

namespace App\Entity\Contract;

use Doctrine\Common\Collections\ReadableCollection;

/**
 * Entité dont les champs textuels sont portés par une entité de traduction par langue (XxxTranslation).
 * La langue de référence est le français : en l'absence de traduction, c'est la version française
 * qui est servie (voir TranslatableTrait::translate()).
 */
interface TranslatableInterface
{
    public const string DEFAULT_LOCALE = 'fr';

    /**
     * @return ReadableCollection<string, TranslationInterface> traductions indexées par langue
     */
    public function getTranslations(): ReadableCollection;

    public function getTranslation(string $locale): ?TranslationInterface;

    public function hasTranslation(string $locale): bool;

    /**
     * Traduction dans la langue demandée, sinon en français, sinon la première disponible.
     *
     * @throws \LogicException si l'entité n'a aucune traduction
     */
    public function translate(string $locale): TranslationInterface;

    /**
     * Vrai si le contenu demandé dans $locale sera servi dans une autre langue (repli).
     */
    public function isTranslationFallback(string $locale): bool;

    /**
     * @throws \InvalidArgumentException si la traduction n'est pas du type attendu par l'entité
     * @throws \LogicException           si une autre traduction existe déjà dans cette langue
     */
    public function addTranslation(TranslationInterface $translation): static;

    public function removeTranslation(TranslationInterface $translation): static;
}
