<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * En-tête de section : surtitre en capitales, titre, lien « Voir tout » optionnel.
 *
 *   <twig:SectionHeading kicker="Actualités" title="Les dernières nouvelles" href="/fr/actualites" />
 */
#[AsTwigComponent]
final class SectionHeading
{
    public string $title;
    public ?string $kicker = null;
    public ?string $href = null;
    /** Libellé du lien ; par défaut « Voir tout » traduit. */
    public ?string $linkLabel = null;
    public int $headingLevel = 2;
    /** Identifiant du titre, pour un aria-labelledby sur la section englobante. */
    public ?string $id = null;

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
        Props::optionalString($resolver, 'kicker', 'href', 'linkLabel', 'id');
        Props::headingLevel($resolver, 2);

        return Props::resolve($resolver, $data);
    }
}
