<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class ForgotPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'admin.field.email',
            'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            'constraints' => [new Assert\NotBlank(message: 'request.error.email_required'), new Assert\Email(message: 'request.error.email_invalid')],
        ]);
    }
}
