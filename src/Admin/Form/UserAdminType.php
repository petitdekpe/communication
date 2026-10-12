<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\User;
use App\Enum\AdminRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte d'administration : nom, e-mail, rôles, actif. Rôles et activation sont verrouillés sur son propre compte
 * (option « self ») : on ne peut ni se retirer le rôle de super administrateur ni se désactiver.
 *
 * @extends AbstractType<User>
 */
final class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'admin.field.name', 'attr' => ['maxlength' => 150, 'autocomplete' => 'off']])
            ->add('email', EmailType::class, ['label' => 'admin.field.email', 'attr' => ['maxlength' => 180, 'autocomplete' => 'off']])
            ->add('roles', ChoiceType::class, [
                'label' => 'admin.field.roles',
                'help' => 'admin.field.roles_help',
                'choices' => array_combine(array_map(static fn (AdminRole $r): string => $r->labelKey(), AdminRole::cases()), array_column(AdminRole::cases(), 'value')),
                'multiple' => true,
                'expanded' => true,
                'disabled' => $options['self'],
                'constraints' => [new Assert\Count(min: 1, minMessage: 'admin.error.role_required')],
            ])
            ->add('active', CheckboxType::class, ['required' => false, 'label' => 'admin.field.active', 'disabled' => $options['self']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'self' => false]);
        $resolver->setAllowedTypes('self', 'bool');
    }
}
