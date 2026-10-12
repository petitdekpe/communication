<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Socle des formulaires de démarche : le formulaire concret ajoute ses champs, puis appelle
 * addCommonFields() pour l'e-mail, les pièces jointes, le consentement et le piège à robots.
 *
 * @extends AbstractType<AbstractRequestData>
 */
abstract class AbstractServiceRequestType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractRequestData|null> $builder
     */
    protected function addCommonFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'request.field.email',
                'help' => 'request.field.email_help',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            ->add('attachments', FileType::class, [
                'label' => 'request.field.attachments',
                'help' => 'request.field.attachments_help',
                'multiple' => true,
                'attr' => ['accept' => 'application/pdf,image/jpeg,.pdf,.jpg,.jpeg'],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'request.field.consent',
                'required' => true,
            ])
            ->add('website', HoneypotType::class);
    }
}
