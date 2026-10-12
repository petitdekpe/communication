<?php

declare(strict_types=1);

namespace App\Newsletter;

use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<NewsletterSubscription>
 */
final class NewsletterSubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'home.newsletter.label',
                'help' => 'home.newsletter.hint',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('website', HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewsletterSubscription::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'newsletter';
    }
}
