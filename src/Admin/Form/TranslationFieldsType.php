<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champs traduits d'une langue (un onglet de LocaleTabs). Donnée : tableau champ => valeur.
 * Option « fields » : champ => [type, label, help?, rows?, required?].
 *
 * @extends AbstractType<array<string, string|null>>
 */
final class TranslationFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['fields'] as $name => $field) {
            $fieldOptions = ['label' => $field['label'], 'required' => $field['required'] ?? false, 'empty_data' => null];
            if (isset($field['help'])) {
                $fieldOptions['help'] = $field['help'];
            }
            if (isset($field['rows'])) {
                $fieldOptions['attr'] = ['rows' => $field['rows']];
            }
            $builder->add($name, $field['type'], $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fields')->setAllowedTypes('fields', 'array');
        $resolver->setDefaults(['label' => false]);
    }
}
