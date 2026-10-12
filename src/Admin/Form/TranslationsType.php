<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Un sous-formulaire par langue (fr, en). Les langues qui ne figurent pas dans « editable » sont désactivées :
 * leurs valeurs ne sont jamais lues dans la requête (traducteur : seul l'anglais est modifiable).
 *
 * @extends AbstractType<array<string, array<string, string|null>>>
 */
final class TranslationsType extends AbstractType
{
    public const array LOCALES = ['fr', 'en'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::LOCALES as $locale) {
            $builder->add($locale, TranslationFieldsType::class, [
                'fields' => $options['fields'],
                'disabled' => !\in_array($locale, $options['editable'], true),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['fields', 'editable']);
        $resolver->setAllowedTypes('fields', 'array')->setAllowedTypes('editable', 'array');
        $resolver->setDefaults(['label' => false]);
    }
}
