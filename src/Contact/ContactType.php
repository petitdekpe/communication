<?php

declare(strict_types=1);

namespace App\Contact;

use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ContactData>
 */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'contact.field.name',
                'attr' => ['autocomplete' => 'name', 'maxlength' => 150],
            ])
            ->add('email', EmailType::class, [
                'label' => 'contact.field.email',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('phone', TelType::class, [
                'label' => 'contact.field.phone',
                'required' => false,
                'attr' => ['autocomplete' => 'tel', 'maxlength' => 40],
            ])
            ->add('subject', TextType::class, [
                'label' => 'contact.field.subject',
                'attr' => ['maxlength' => 200],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.field.message',
                'attr' => ['rows' => 8, 'maxlength' => 5000],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'contact.field.consent',
                'required' => true,
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactData::class]);
    }
}
