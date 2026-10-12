<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * État vide (aucun résultat, liste vide) : icône, titre, message, action facultative.
 */
#[AsTwigComponent]
final class EmptyState
{
    public string $title;
    public ?string $message = null;
    public string $icon = 'ri:inbox-2-line';
    public ?string $actionLabel = null;
    public ?string $actionHref = null;
    public int $headingLevel = 2;

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
        Props::optionalString($resolver, 'message', 'actionLabel', 'actionHref');
        $resolver->setDefault('icon', 'ri:inbox-2-line')->setAllowedTypes('icon', 'string');
        Props::headingLevel($resolver, 2);

        $resolved = Props::resolve($resolver, $data);
        Props::assertPaired($resolved, 'actionLabel', 'actionHref');

        return $resolved;
    }
}
