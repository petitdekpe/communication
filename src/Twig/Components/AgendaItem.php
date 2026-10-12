<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Événement d'agenda : bloc jour + mois, titre, lieu.
 */
#[AsTwigComponent]
final class AgendaItem
{
    public \DateTimeInterface $date;
    public string $title;
    public ?string $location = null;
    public ?string $href = null;
    /** Ligne d'informations complémentaires (nature, horaires). */
    public ?string $meta = null;
    public int $headingLevel = 3;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        Props::date($resolver, 'date');
        Props::requiredString($resolver, 'title');
        Props::optionalString($resolver, 'location', 'href', 'meta');
        Props::headingLevel($resolver, 3);

        return Props::resolve($resolver, $data);
    }
}
