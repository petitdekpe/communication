<?php

declare(strict_types=1);

namespace App\Twig;

use App\Routing\AlternateUrlResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final class RoutingExtension
{
    public function __construct(
        private readonly AlternateUrlResolver $resolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * URL de la page courante dans chaque langue : {{ alternate_urls().en }}.
     *
     * @return array<string, string>
     */
    #[AsTwigFunction('alternate_urls')]
    public function alternateUrls(): array
    {
        return $this->resolver->resolve($this->requestStack->getMainRequest());
    }
}
