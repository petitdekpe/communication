<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Bandeau d'ouverture : surtitre, titre (Baskervville), chapô, image, deux boutons au plus.
 * Fond crème : le titre et le texte restent en bleu nuit / encre (jamais de texte blanc sur crème).
 */
#[AsTwigComponent]
final class Hero
{
    public string $title;
    public ?string $kicker = null;
    public ?string $lead = null;
    public ?string $image = null;
    public ?string $imageAlt = null;
    public ?string $primaryLabel = null;
    public ?string $primaryHref = null;
    public ?string $secondaryLabel = null;
    public ?string $secondaryHref = null;
    public int $headingLevel = 1;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        Props::requiredString($resolver, 'title');
        Props::optionalString($resolver, 'kicker', 'lead', 'image', 'primaryLabel', 'primaryHref', 'secondaryLabel', 'secondaryHref');
        $resolver->setDefault('imageAlt', null)->setAllowedTypes('imageAlt', ['null', 'string']);
        Props::headingLevel($resolver, 1);

        $resolved = Props::resolve($resolver, $data);
        Props::assertPaired($resolved, 'image', 'imageAlt');
        Props::assertPaired($resolved, 'primaryLabel', 'primaryHref');
        Props::assertPaired($resolved, 'secondaryLabel', 'secondaryHref');

        return $resolved;
    }
}
