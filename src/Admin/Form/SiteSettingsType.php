<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Settings\SiteSettingsData;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Paramètres du site. Le code de suivi d'audience (script exécuté chez tous les visiteurs) n'est modifiable que par
 * le super administrateur, seul rôle à atteindre cette page.
 *
 * @extends AbstractType<SiteSettingsData>
 */
final class SiteSettingsType extends AbstractType
{
    public function __construct(
        #[Autowire('%app.timezone%')]
        private readonly string $timezone,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $text = static fn (string $label, string $help = '', int $max = 255): array => ['required' => false, 'label' => $label, 'help' => '' === $help ? null : $help, 'attr' => ['maxlength' => $max]];
        $url = static fn (string $label): array => ['required' => false, 'label' => $label, 'default_protocol' => null, 'attr' => ['maxlength' => 255, 'placeholder' => 'https://']];
        $date = fn (string $label): array => ['required' => false, 'label' => $label, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone];

        $builder
            ->add('address', TextType::class, $text('admin.settings.address'))
            ->add('phone', TextType::class, $text('admin.settings.phone', '', 50))
            ->add('email', EmailType::class, $text('admin.settings.email', '', 180))
            ->add('supportPhone', TextType::class, $text('admin.settings.support_phone', 'admin.settings.support_phone_help', 50))
            ->add('facebook', UrlType::class, $url('admin.settings.facebook'))
            ->add('x', UrlType::class, $url('admin.settings.x'))
            ->add('youtube', UrlType::class, $url('admin.settings.youtube'))
            ->add('linkedin', UrlType::class, $url('admin.settings.linkedin'))
            ->add('ministerFacebook', UrlType::class, $url('admin.settings.facebook'))
            ->add('ministerX', UrlType::class, $url('admin.settings.x'))
            ->add('ministerLinkedin', UrlType::class, $url('admin.settings.linkedin'))
            ->add('noticeFr', TextareaType::class, ['required' => false, 'label' => 'admin.settings.notice_fr', 'help' => 'admin.settings.notice_help', 'attr' => ['rows' => 3, 'maxlength' => 400, 'lang' => 'fr']])
            ->add('noticeEn', TextareaType::class, ['required' => false, 'label' => 'admin.settings.notice_en', 'attr' => ['rows' => 3, 'maxlength' => 400, 'lang' => 'en']])
            ->add('noticeLink', TextType::class, $text('admin.settings.notice_link', 'admin.settings.notice_link_help'))
            ->add('noticeStartsAt', DateTimeType::class, $date('admin.settings.notice_starts'))
            ->add('noticeEndsAt', DateTimeType::class, $date('admin.settings.notice_ends'))
            ->add('agendaEnabled', CheckboxType::class, ['required' => false, 'label' => 'admin.settings.agenda', 'help' => 'admin.settings.agenda_help'])
            ->add('analyticsCode', TextareaType::class, ['required' => false, 'label' => 'admin.settings.analytics', 'help' => 'admin.settings.analytics_help', 'attr' => ['rows' => 6, 'maxlength' => 5000, 'spellcheck' => 'false', 'class' => 'font-mono']])
            ->add('retentionRequests', IntegerType::class, ['required' => false, 'label' => 'admin.settings.retention_requests', 'help' => 'admin.settings.retention_help', 'attr' => ['min' => 1, 'max' => 240]])
            ->add('retentionContributions', IntegerType::class, ['required' => false, 'label' => 'admin.settings.retention_contributions', 'attr' => ['min' => 1, 'max' => 240]])
            ->add('retentionSubscribers', IntegerType::class, ['required' => false, 'label' => 'admin.settings.retention_subscribers', 'attr' => ['min' => 1, 'max' => 240]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteSettingsData::class]);
    }
}
