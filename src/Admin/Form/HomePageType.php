<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Home\HomePageData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Textes de la page d'accueil, en français et en anglais.
 *
 * @extends AbstractType<HomePageData>
 */
final class HomePageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $line = static fn (string $label, string $locale, int $max, string $help = ''): array => ['required' => false, 'label' => $label, 'help' => '' === $help ? null : $help, 'attr' => ['maxlength' => $max, 'lang' => $locale]];
        $area = static fn (string $label, string $locale, int $max, int $rows = 3): array => ['required' => false, 'label' => $label, 'attr' => ['maxlength' => $max, 'rows' => $rows, 'lang' => $locale]];

        $builder
            ->add('heroTitleFr', TextType::class, $line('admin.home.hero_title_fr', 'fr', 160, 'admin.home.hero_title_help'))
            ->add('heroTitleEn', TextType::class, $line('admin.home.hero_title_en', 'en', 160))
            ->add('heroLeadFr', TextareaType::class, $area('admin.home.hero_lead_fr', 'fr', 400))
            ->add('heroLeadEn', TextareaType::class, $area('admin.home.hero_lead_en', 'en', 400))
            ->add('newsletterTextFr', TextareaType::class, $area('admin.home.newsletter_text_fr', 'fr', 300, 2))
            ->add('newsletterTextEn', TextareaType::class, $area('admin.home.newsletter_text_en', 'en', 300, 2))
            ->add('newsletterConsentFr', TextareaType::class, $area('admin.home.newsletter_consent_fr', 'fr', 400, 2))
            ->add('newsletterConsentEn', TextareaType::class, $area('admin.home.newsletter_consent_en', 'en', 400, 2));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => HomePageData::class]);
    }
}
