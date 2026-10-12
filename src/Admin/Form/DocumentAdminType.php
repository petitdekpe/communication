<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Enum\DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<DocumentData>
 */
final class DocumentAdminType extends AbstractType
{
    public const array FIELDS = [
        'title' => ['type' => TextType::class, 'label' => 'admin.field.title', 'required' => true],
        'summary' => ['type' => TextareaType::class, 'label' => 'admin.field.summary', 'rows' => 4],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DocumentData $data */
        $data = $builder->getData();
        $details = !$options['details_disabled'];

        $builder
            ->add('type', EnumType::class, ['class' => DocumentType::class, 'label' => 'admin.field.type', 'choice_label' => static fn (DocumentType $t): string => $t->labelKey(), 'disabled' => !$details])
            ->add('reference', TextType::class, ['required' => false, 'label' => 'admin.field.reference', 'help' => 'admin.field.reference_help', 'disabled' => !$details])
            ->add('documentDate', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'label' => 'admin.field.document_date', 'disabled' => !$details])
            ->add('fileLanguage', ChoiceType::class, ['choices' => ['language.fr' => 'fr', 'language.en' => 'en'], 'label' => 'admin.field.file_language', 'disabled' => !$details])
            ->add('file', FileType::class, ['required' => $data->fileRequired, 'label' => 'admin.field.file', 'help' => 'admin.field.file_help', 'disabled' => !$details])
            ->add('translations', TranslationsType::class, ['fields' => self::FIELDS, 'editable' => $data->editable]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DocumentData::class, 'details_disabled' => false]);
    }
}
