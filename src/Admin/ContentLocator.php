<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Contract\PublishableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Retrouve un contenu publiable à partir de l'identifiant de ressource de l'administration (« articles », « documents »)
 * et fournit les jetons CSRF des actions du formulaire (circuit de publication, versions, verrou).
 */
final class ContentLocator
{
    public function __construct(
        private readonly AdminResources $resources,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    /**
     * @throws NotFoundHttpException
     */
    public function resource(string $resource): AdminResource
    {
        $definition = $this->resources->get($resource);
        if (null === $definition || !$definition->workflow) {
            throw new NotFoundHttpException();
        }

        return $definition;
    }

    public function resourceFor(PublishableInterface $content): ?AdminResource
    {
        return $this->resources->forEntity($content);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function find(string $resource, int $id): PublishableInterface
    {
        $content = $this->entityManager->find($this->resource($resource)->entity, $id);

        return $content instanceof PublishableInterface ? $content : throw new NotFoundHttpException();
    }

    public function token(string $scope, string $resource, int $id): string
    {
        return $this->csrf->getToken($this->tokenId($scope, $resource, $id))->getValue();
    }

    public function isValidToken(string $scope, string $resource, int $id, ?string $value): bool
    {
        return $this->csrf->isTokenValid(new CsrfToken($this->tokenId($scope, $resource, $id), (string) $value));
    }

    private function tokenId(string $scope, string $resource, int $id): string
    {
        return 'content_'.$scope.'_'.$resource.'_'.$id;
    }
}
