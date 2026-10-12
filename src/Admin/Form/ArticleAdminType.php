<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\Category;
use App\Enum\ArticleType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ArticleData>
 */
final class ArticleAdminType extends AbstractType
{
    /** Champs traduits d'un article. */
    public const array FIELDS = [
        'title' => ['type' => TextType::class, 'label' => 'admin.field.title', 'required' => true],
        'excerpt' => ['type' => TextareaType::class, 'label' => 'admin.field.excerpt', 'rows' => 3],
        'body' => ['type' => TextareaType::class, 'label' => 'admin.field.body', 'help' => 'admin.field.body_help', 'rows' => 14],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ArticleData $data */
        $data = $builder->getData();
        $details = !$options['details_disabled'];

        $builder
            ->add('type', EnumType::class, ['class' => ArticleType::class, 'label' => 'admin.field.type', 'choice_label' => static fn (ArticleType $t): string => 'admin.enum.article_type.'.$t->value, 'disabled' => !$details])
            ->add('category', EntityType::class, ['class' => Category::class, 'required' => false, 'placeholder' => 'admin.field.no_category', 'label' => 'admin.field.category', 'choice_label' => static fn (Category $c): string => (string) $c->translate('fr')->getName(), 'disabled' => !$details])
            ->add('featured', CheckboxType::class, ['required' => false, 'label' => 'admin.field.featured', 'disabled' => !$details])
            ->add('coverImage', HiddenType::class, ['required' => false, 'disabled' => !$details])
            ->add('translations', TranslationsType::class, ['fields' => self::FIELDS, 'editable' => $data->editable]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ArticleData::class, 'details_disabled' => false]);
    }
}
