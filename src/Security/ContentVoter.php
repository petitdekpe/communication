<?php

declare(strict_types=1);

namespace App\Security;

use App\Admin\AdminResource;
use App\Admin\AdminResources;
use App\Entity\Contract\PublishableInterface;
use App\Enum\PublicationStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur les contenus. Le sujet est une entité (droit sur CE contenu : il dépend de son statut), son nom de
 * classe (droit en général : « peut-on en créer ? ») ou une AdminResource (accès à la liste).
 *
 *  - VIEW      : éditeur, relecteur (par héritage), traducteur, admin.
 *  - CREATE    : éditeur et au-dessus.
 *  - EDIT      : l'éditeur modifie un brouillon ; le relecteur et l'admin modifient tout ce qui n'est pas archivé.
 *  - SUBMIT    : éditeur et au-dessus, pour un brouillon.
 *  - PUBLISH   : relecteur et au-dessus (publier, dépublier, rejeter, archiver).
 *  - DELETE    : admin.
 *  - TRANSLATE : traducteur et admin, pour tout contenu non archivé.
 * Chaque ressource fixe en outre le rôle minimal pour y accéder (Démarches, Pages… : admin ; Paramètres : super admin).
 *
 * @extends Voter<string, mixed>
 */
final class ContentVoter extends Voter
{
    private const array ATTRIBUTES = [
        Permission::CONTENT_VIEW, Permission::CONTENT_CREATE, Permission::CONTENT_EDIT, Permission::CONTENT_SUBMIT,
        Permission::CONTENT_PUBLISH, Permission::CONTENT_DELETE, Permission::CONTENT_TRANSLATE, Permission::RESOURCE_VIEW,
    ];

    /** Permissions ouvertes aux traducteurs sur les contenus éditoriaux, en plus du rôle minimal de la ressource. */
    private const array TRANSLATOR_READ = [Permission::CONTENT_VIEW, Permission::RESOURCE_VIEW, Permission::CONTENT_TRANSLATE];

    public function __construct(
        private readonly Security $security,
        private readonly AdminResources $resources,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        return $subject instanceof AdminResource
            || $subject instanceof PublishableInterface
            || (\is_string($subject) && null !== $this->resources->forEntity($subject));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $resource = $subject instanceof AdminResource ? $subject : $this->resources->forEntity($subject);
        if (null === $resource || !$this->reachable($resource, $attribute)) {
            return false;
        }

        $status = $subject instanceof PublishableInterface ? $subject->getStatus() : null;
        $editor = $this->security->isGranted('ROLE_EDITOR');
        $reviewer = $this->security->isGranted('ROLE_REVIEWER');

        return match ($attribute) {
            Permission::RESOURCE_VIEW, Permission::CONTENT_VIEW => true,
            Permission::CONTENT_CREATE => $resource->workflow && $editor,
            Permission::CONTENT_EDIT => $editor && (null === $status || ($reviewer ? PublicationStatus::Archived !== $status : PublicationStatus::Draft === $status)),
            Permission::CONTENT_SUBMIT => $resource->workflow && $editor && (null === $status || PublicationStatus::Draft === $status),
            Permission::CONTENT_PUBLISH => $resource->workflow && $reviewer,
            Permission::CONTENT_DELETE => $this->security->isGranted('ROLE_ADMIN'),
            Permission::CONTENT_TRANSLATE => $resource->translatable && $this->security->isGranted('ROLE_TRANSLATOR') && PublicationStatus::Archived !== $status,
            default => false,
        };
    }

    private function reachable(AdminResource $resource, string $attribute): bool
    {
        if ($this->security->isGranted($resource->role)) {
            return true;
        }

        return 'ROLE_EDITOR' === $resource->role
            && \in_array($attribute, self::TRANSLATOR_READ, true)
            && $this->security->isGranted('ROLE_TRANSLATOR');
    }
}
