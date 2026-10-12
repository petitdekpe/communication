<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Chiffre clé sourcé. La source et la date de mise à jour sont obligatoires : un chiffre sans source
 * n'est pas publiable (le composant lève une erreur).
 */
#[AsTwigComponent]
final class StatCard
{
    /** Nombre (formaté selon la langue) ou texte déjà mis en forme. */
    public int|float|string $value;
    public string $label;
    public string $source;
    public \DateTimeInterface $updatedAt;
    public ?string $sourceUrl = null;
    /** Unité affichée après la valeur (%, Mds FCFA…). */
    public ?string $unit = null;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        $resolver->setRequired('value')->setAllowedTypes('value', ['int', 'float', 'string']);
        Props::requiredString($resolver, 'label', 'source');
        Props::date($resolver, 'updatedAt');
        Props::optionalString($resolver, 'sourceUrl', 'unit');

        return Props::resolve($resolver, $data);
    }

    public function isNumeric(): bool
    {
        return \is_int($this->value) || \is_float($this->value);
    }
}
