<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\Component\OptionsResolver\Options;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Fil d'Ariane. Le dernier élément est la page courante (aria-current, sans lien).
 *
 *   <twig:Breadcrumb :items="[{label: 'Accueil', href: '/fr'}, {label: 'Actualités', href: '/fr/actualites'}, {label: 'Titre'}]" />
 */
#[AsTwigComponent]
final class Breadcrumb
{
    /** @var list<array{label: string, href: string|null}> */
    public array $items;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        $resolver
            ->setRequired('items')
            ->setAllowedTypes('items', 'array[]')
            ->setAllowedValues('items', static function (array $items): bool {
                if ([] === $items || !array_is_list($items)) {
                    return false;
                }
                foreach ($items as $index => $item) {
                    $isLast = $index === \count($items) - 1;
                    if (!\is_string($item['label'] ?? null) || '' === $item['label']) {
                        return false;
                    }
                    // Chaque niveau sauf la page courante doit être un lien.
                    if (!$isLast && !\is_string($item['href'] ?? null)) {
                        return false;
                    }
                }

                return true;
            })
            ->setNormalizer('items', static fn (Options $o, array $items): array => array_map(
                static fn (array $item): array => ['label' => $item['label'], 'href' => $item['href'] ?? null],
                $items,
            ));

        return Props::resolve($resolver, $data);
    }
}
