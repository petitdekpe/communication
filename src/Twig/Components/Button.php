<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\ButtonSize;
use App\Enum\ButtonVariant;
use App\Twig\Components\Support\Props;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Bouton en pastille. Rendu <a> si href est fourni, <button> sinon.
 *
 *   <twig:Button label="Déposer un dossier" variant="accent" size="lg" icon="ri:arrow-right-line" iconPosition="end" href="/fr/…" />
 */
#[AsTwigComponent]
final class Button
{
    public string $label;
    public ButtonVariant $variant = ButtonVariant::Primary;
    public ButtonSize $size = ButtonSize::Md;
    public ?string $href = null;
    /** Nom ux-icon, ex. "ri:download-2-line". */
    public ?string $icon = null;
    /** 'start' | 'end' */
    public string $iconPosition = 'start';
    /** Libellé masqué visuellement (conservé pour les lecteurs d'écran) : bouton icône seule. */
    public bool $iconOnly = false;
    /** 'button' | 'submit' | 'reset' (rendu <button> uniquement). */
    public string $type = 'button';
    public bool $disabled = false;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        Props::requiredString($resolver, 'label');
        Props::enum($resolver, 'variant', ButtonVariant::class, ButtonVariant::Primary);
        Props::enum($resolver, 'size', ButtonSize::class, ButtonSize::Md);
        Props::optionalString($resolver, 'href', 'icon');
        Props::bool($resolver, 'iconOnly', false);
        Props::bool($resolver, 'disabled', false);
        $resolver->setDefault('iconPosition', 'start')->setAllowedValues('iconPosition', ['start', 'end']);
        $resolver->setDefault('type', 'button')->setAllowedValues('type', ['button', 'submit', 'reset']);

        $resolved = Props::resolve($resolver, $data);

        if ($resolved['iconOnly'] && null === $resolved['icon']) {
            throw new InvalidOptionsException('Un bouton "iconOnly" doit avoir une icône.');
        }
        if ($resolved['disabled'] && null !== $resolved['href']) {
            throw new InvalidOptionsException('Un lien ne peut pas être désactivé : retirer "href" ou "disabled".');
        }

        return $resolved;
    }

    public function getCssClass(): string
    {
        return implode(' ', array_filter([
            'btn',
            $this->variant->cssClass(),
            $this->size->cssClass(),
            $this->iconOnly ? 'btn-icon' : null,
        ]));
    }
}
