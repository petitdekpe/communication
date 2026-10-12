<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Contenu d'une page : titre, texte, titre et description pour les moteurs de recherche (dans chaque langue).
 *
 * @extends AbstractType<PageData>
 */
final class PageAdminType extends AbstractType
{
    /** Champs traduits d'une page. */
    public const array FIELDS = [
        'title' => ['type' => TextType::class, 'label' => 'admin.field.title', 'required' => true],
        'body' => ['type' => TextareaType::class, 'label' => 'admin.field.body', 'help' => 'admin.field.body_help', 'rows' => 14],
        'metaTitle' => ['type' => TextType::class, 'label' => 'page_editor.field.meta_title', 'help' => 'page_editor.field.meta_title_help'],
        'metaDescription' => ['type' => TextareaType::class, 'label' => 'page_editor.field.meta_description', 'help' => 'page_editor.field.meta_description_help', 'rows' => 3],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('translations', TranslationsType::class, ['fields' => self::FIELDS, 'editable' => ['fr', 'en']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PageData::class]);
    }
}
