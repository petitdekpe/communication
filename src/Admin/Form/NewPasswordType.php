<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nouveau mot de passe : 12 caractères au moins, saisi deux fois.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'admin.password.mismatch',
            'first_options' => [
                'label' => 'admin.field.new_password',
                'help' => 'admin.field.new_password_help',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'request.error.required'),
                    new Assert\Length(min: 12, max: 4096, minMessage: 'admin.password.too_short'),
                    new Assert\NotCompromisedPassword(message: 'admin.password.compromised', skipOnError: true),
                ],
            ],
            'second_options' => ['label' => 'admin.field.repeat_password', 'attr' => ['autocomplete' => 'new-password']],
        ]);
    }
}
