<?php

declare(strict_types=1);

namespace App\ServiceRequest\Accreditation;

use App\ServiceRequest\AbstractServiceRequestType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccreditationFormType extends AbstractServiceRequestType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, [
                'label' => 'request.accreditation.last_name',
                'attr' => ['autocomplete' => 'family-name', 'maxlength' => 100],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'request.accreditation.first_name',
                'attr' => ['autocomplete' => 'given-name', 'maxlength' => 100],
            ])
            ->add('phone', TextType::class, [
                'label' => 'request.accreditation.phone',
                'required' => false,
                'attr' => ['autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 30],
            ])
            ->add('mediaOutlet', TextType::class, [
                'label' => 'request.accreditation.media_outlet',
                'attr' => ['autocomplete' => 'organization', 'maxlength' => 150],
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'request.accreditation.job_title',
                'attr' => ['autocomplete' => 'organization-title', 'maxlength' => 100],
            ])
            ->add('eventName', TextType::class, [
                'label' => 'request.accreditation.event_name',
                'help' => 'request.accreditation.event_name_help',
                'attr' => ['maxlength' => 200],
            ]);

        $this->addCommonFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccreditationData::class]);
    }
}
