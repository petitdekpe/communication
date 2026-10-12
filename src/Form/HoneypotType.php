<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Piège à robots : champ rendu hors écran et inerte (invisible, hors parcours clavier et lecteurs d'écran).
 * Un robot qui remplit tous les champs le remplit aussi ; la contrainte Blank de la donnée rejette alors l'envoi.
 *
 * @extends AbstractType<string>
 */
final class HoneypotType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => 'request.honeypot.label',
            'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'honeypot';
    }
}
