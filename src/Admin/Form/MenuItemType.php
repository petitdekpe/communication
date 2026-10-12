<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\MenuItem;
use App\Entity\MenuItemTranslation;
use App\Menu\MenuItemData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Entrée de menu : libellés FR/EN, type de lien (aucun, contenu du site, adresse externe, chemin interne) et, pour un
 * menu à deux niveaux, le groupe parent. Le champ « target » est rempli par la recherche de contenu (content-search).
 *
 * @extends AbstractType<MenuItemData>
 */
final class MenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('labelFr', TextType::class, ['label' => 'admin.menus.label_fr', 'attr' => ['maxlength' => 150, 'lang' => 'fr']])
            ->add('labelEn', TextType::class, ['required' => false, 'label' => 'admin.menus.label_en', 'help' => 'admin.menus.label_en_help', 'attr' => ['maxlength' => 150, 'lang' => 'en']])
            ->add('linkType', ChoiceType::class, [
                'label' => 'admin.menus.link_type',
                'expanded' => true,
                'choices' => [
                    'admin.menus.link.none' => MenuItemData::LINK_NONE,
                    'admin.menus.link.content' => MenuItemData::LINK_CONTENT,
                    'admin.menus.link.url' => MenuItemData::LINK_URL,
                    'admin.menus.link.path' => MenuItemData::LINK_PATH,
                ],
            ])
            ->add('target', HiddenType::class, ['required' => false])
            ->add('url', UrlType::class, ['required' => false, 'label' => 'admin.menus.url', 'default_protocol' => null, 'attr' => ['maxlength' => 255, 'placeholder' => 'https://']])
            ->add('pathFr', TextType::class, ['required' => false, 'label' => 'admin.menus.path_fr', 'help' => 'admin.menus.path_help', 'attr' => ['maxlength' => 255, 'placeholder' => '/fr/…']])
            ->add('pathEn', TextType::class, ['required' => false, 'label' => 'admin.menus.path_en', 'attr' => ['maxlength' => 255, 'placeholder' => '/en/…']]);

        if ([] !== $options['parents']) {
            $builder->add('parent', EntityType::class, [
                'class' => MenuItem::class,
                'choices' => $options['parents'],
                'required' => false,
                'placeholder' => 'admin.menus.no_parent',
                'label' => 'admin.menus.parent',
                'choice_label' => static fn (MenuItem $item): string => (string) ($item->getTranslation('fr') instanceof MenuItemTranslation ? $item->getTranslation('fr')->getLabel() : $item->getId()),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MenuItemData::class, 'parents' => []]);
        $resolver->setAllowedTypes('parents', 'array');
    }
}
