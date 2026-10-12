<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TrackingLookup>
 */
final class TrackingLookupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', TextType::class, [
                'label' => 'tracking.field.number',
                'help' => 'tracking.field.number_help',
                'attr' => ['autocomplete' => 'off', 'maxlength' => 40, 'autocapitalize' => 'characters', 'spellcheck' => 'false'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'tracking.field.email',
                'help' => 'tracking.field.email_help',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TrackingLookup::class]);
    }
}
