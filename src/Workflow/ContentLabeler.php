<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Admin\AdminResources;
use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Titre lisible et adresse d'administration d'un contenu publiable (e-mails, historique).
 */
final class ContentLabeler
{
    private readonly PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly AdminResources $resources,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function label(PublishableInterface $content, string $locale = 'fr'): string
    {
        if ($content instanceof TranslatableInterface && !$content->getTranslations()->isEmpty()) {
            $translation = $content->translate($locale);
            foreach (['title', 'label', 'name'] as $field) {
                $value = $this->accessor->isReadable($translation, $field) ? $this->accessor->getValue($translation, $field) : null;
                if (\is_string($value) && '' !== $value) {
                    return $value;
                }
            }
        }

        return '#'.($this->accessor->isReadable($content, 'id') ? (string) $this->accessor->getValue($content, 'id') : '?');
    }

    public function adminUrl(PublishableInterface $content): ?string
    {
        $resource = $this->resources->forEntity($content);
        if (null === $resource) {
            return null;
        }
        $id = $this->accessor->isReadable($content, 'id') ? $this->accessor->getValue($content, 'id') : null;
        if (null !== $resource->editRoute && \is_int($id)) {
            return $this->urlGenerator->generate($resource->editRoute, ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->urlGenerator->generate('app_admin_resource_list', ['resource' => $resource->id], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
