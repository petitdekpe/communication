<?php

declare(strict_types=1);

namespace App\Consultation;

use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ContributionData>
 */
final class ContributionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'consultation.field.name',
                'attr' => ['autocomplete' => 'name', 'maxlength' => 150],
            ])
            ->add('organization', TextType::class, [
                'label' => 'consultation.field.organization',
                'required' => false,
                'attr' => ['autocomplete' => 'organization', 'maxlength' => 200],
            ])
            ->add('text', TextareaType::class, [
                'label' => 'consultation.field.text',
                'help' => 'consultation.field.text_help',
                'attr' => ['rows' => 8, 'maxlength' => 10000],
            ])
            ->add('file', FileType::class, [
                'label' => 'consultation.field.file',
                'help' => 'consultation.field.file_help',
                'required' => false,
                'attr' => ['accept' => 'application/pdf,image/jpeg,.pdf,.jpg,.jpeg'],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'consultation.field.consent',
                'required' => true,
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContributionData::class]);
    }
}
