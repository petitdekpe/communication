<?php

declare(strict_types=1);

namespace App\Twig;

use App\Settings\SiteSettings;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Paramètres du site dans les gabarits : {{ site_setting('contact.phone') }} (vide si non renseigné),
 * {{ site_setting_or_pending('contact.phone') }} (marqueur [À VALIDER] si non renseigné).
 */
final class SiteSettingsExtension
{
    public function __construct(
        private readonly SiteSettings $settings,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction('site_setting')]
    public function get(string $name): ?string
    {
        return $this->settings->get($name, $this->locale());
    }

    #[AsTwigFunction('site_setting_or_pending')]
    public function orPending(string $name): string
    {
        return $this->settings->orPending($name, $this->locale());
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }
}
