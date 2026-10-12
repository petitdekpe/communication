<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\Audience;
use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Carte de démarche : public, titre, résumé, coût, délai, disponibilité en ligne, lien.
 * Coût et délai non renseignés s'affichent « Non précisé » (jamais de valeur inventée).
 */
#[AsTwigComponent]
final class ServiceCard
{
    public Audience $audience;
    public string $title;
    public string $summary;
    public string $href;
    public bool $online;
    public ?string $cost = null;
    public ?string $delay = null;
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
        Props::enum($resolver, 'audience', Audience::class);
        Props::requiredString($resolver, 'title', 'summary', 'href');
        $resolver->setRequired('online')->setAllowedTypes('online', 'bool');
        Props::optionalString($resolver, 'cost', 'delay');
        Props::headingLevel($resolver, 3);

        return Props::resolve($resolver, $data);
    }
}
