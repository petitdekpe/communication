<?php

declare(strict_types=1);

namespace App\Preview;

use App\Admin\AdminResources;
use App\Entity\Contract\PublishableInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Liens d'aperçu signés (UriSigner) et valables 24 heures : seul moyen de voir un contenu non publié avec le rendu public.
 */
final class PreviewLinks
{
    public const string VALIDITY = '+24 hours';

    public function __construct(
        private readonly UriSigner $uriSigner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdminResources $resources,
        private readonly ClockInterface $clock,
    ) {
    }

    public function supports(PublishableInterface $content): bool
    {
        $resource = $this->resources->forEntity($content);

        return null !== $resource && \in_array($resource->id, PreviewRenderer::RESOURCES, true);
    }

    public function url(PublishableInterface $content, string $locale): string
    {
        $resource = $this->resources->forEntity($content) ?? throw new \LogicException('Contenu sans ressource d\'administration.');
        $id = method_exists($content, 'getId') ? $content->getId() : null;

        $url = $this->urlGenerator->generate('app_preview', ['_locale' => $locale, 'resource' => $resource->id, 'id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url, $this->clock->now()->modify(self::VALIDITY));
    }
}
