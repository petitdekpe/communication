<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\ServiceRequest;
use App\Enum\AdminRole;
use App\Security\Permission;
use App\Workflow\PublicationWorkflow;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Transforme les entités d'une liste en lignes prêtes à afficher par le composant DataTable : cellules typées
 * et actions de ligne, chacune déjà filtrée par les Voters (le template n'a aucune décision à prendre).
 *
 * @phpstan-type Action array{name: string, label: string, method: string, url: string, token: string, confirm: bool, danger: bool}
 * @phpstan-type Row array{id: int|null, title: string, cells: array<string, array<string, mixed>>, actions: list<Action>}
 */
final class AdminRowFactory
{
    private readonly PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly PublicationWorkflow $transitions,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @return Row
     */
    public function row(AdminResource $resource, object $entity, string $locale): array
    {
        $id = $this->accessor->getValue($entity, 'id');
        $cells = [];
        $title = '';
        foreach ($resource->columns as $column) {
            $cell = $this->cell($resource, $entity, $column, $locale);
            $cells[$column['key']] = $cell;
            if ('' === $title && \is_string($cell['value'] ?? null)) {
                $title = $cell['value'];
            }
        }
        $id = \is_int($id) ? $id : null;
        if (isset($cells['title']) && null !== $id) {
            $cells['title']['href'] = $this->primaryLink($resource, $entity, $id);
        } elseif (null !== $id && null !== $link = $this->primaryLink($resource, $entity, $id)) {
            $first = array_key_first($cells);
            $cells[$first]['href'] = $link;
            $cells[$first]['type'] = 'title' === $cells[$first]['type'] ? 'title' : 'text';
        }

        return ['id' => $id, 'title' => $title, 'cells' => $cells, 'actions' => $this->actions($resource, $entity, $id)];
    }

    /**
     * Actions groupées proposées au-dessus du tableau.
     *
     * @return list<array{name: string, label: string}>
     */
    public function bulkActions(AdminResource $resource): array
    {
        if (!$resource->workflow || !$this->security->isGranted(Permission::CONTENT_SUBMIT, $resource->entity)) {
            return [];
        }

        $actions = [['name' => 'submit', 'label' => 'admin.action.submit']];
        if ($this->security->isGranted(Permission::CONTENT_PUBLISH, $resource->entity)) {
            $actions = [...$actions, ['name' => 'publish', 'label' => 'admin.action.publish'], ['name' => 'unpublish', 'label' => 'admin.action.unpublish']];
        }

        return $actions;
    }

    public function token(AdminResource $resource, string $suffix): string
    {
        return $this->csrf->getToken($this->tokenId($resource, $suffix))->getValue();
    }

    public function tokenId(AdminResource $resource, string $suffix): string
    {
        return 'admin_'.$resource->id.'_'.$suffix;
    }

    public function isValidToken(AdminResource $resource, string $suffix, ?string $value): bool
    {
        return $this->csrf->isTokenValid(new CsrfToken($this->tokenId($resource, $suffix), (string) $value));
    }

    /**
     * @param array{key: string, label: string, path: string, type: string, sort?: string|null} $column
     *
     * @return array<string, mixed>
     */
    private function cell(AdminResource $resource, object $entity, array $column, string $locale): array
    {
        $type = $column['type'];

        if ('title' === $type) {
            $translation = $entity instanceof TranslatableInterface ? $entity->translate($locale) : null;
            $value = null === $translation ? null : $this->read($translation, $column['path']);

            return ['type' => 'title', 'value' => \is_string($value) ? $value : null, 'lang' => $translation?->getLocale(), 'href' => null];
        }

        if ('related-title' === $type) {
            $related = $this->read($entity, $column['path']);
            $translation = $related instanceof TranslatableInterface ? $related->translate($locale) : null;
            $value = null === $translation ? null : $this->read($translation, 'title');

            return ['type' => 'text', 'value' => \is_string($value) ? $value : null, 'lang' => $translation?->getLocale()];
        }

        $value = $this->read($entity, $column['path']);

        return match ($type) {
            'status' => ['type' => 'status', 'value' => $value instanceof \BackedEnum ? $value->value : (string) $value],
            'request-status' => ['type' => 'request-status', 'value' => $value instanceof \BackedEnum ? $value->value : (string) $value],
            'enum' => ['type' => 'enum', 'value' => $value instanceof \BackedEnum ? $value->value : $value, 'label' => $value instanceof \BackedEnum ? $this->enumLabel($value) : ''],
            'bool' => ['type' => 'bool', 'value' => (bool) $value],
            'date', 'datetime' => ['type' => $type, 'value' => $value instanceof \DateTimeInterface ? $value : null],
            'roles' => ['type' => 'roles', 'labels' => array_values(array_map(
                static fn (string $role): string => 'admin.role.'.$role,
                array_filter((array) $value, static fn (string $r): bool => null !== AdminRole::tryFrom($r)),
            ))],
            default => ['type' => 'text', 'value' => \is_scalar($value) ? (string) $value : null, 'lang' => null],
        };
    }

    private function read(object $subject, string $path): mixed
    {
        return $this->accessor->isReadable($subject, $path) ? $this->accessor->getValue($subject, $path) : null;
    }

    private function enumLabel(\BackedEnum $enum): string
    {
        if (method_exists($enum, 'labelKey')) {
            return (string) $enum->labelKey();
        }

        return 'admin.enum.'.strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($enum))->getShortName())).'.'.$enum->value;
    }

    private function primaryLink(AdminResource $resource, object $entity, int $id): ?string
    {
        if (null !== $resource->showRoute && $this->canShow($resource, $entity)) {
            return $this->urlGenerator->generate($resource->showRoute, ['id' => $id]);
        }
        if (null !== $resource->editRoute && $this->canOpen($resource, $entity)) {
            return $this->urlGenerator->generate($resource->editRoute, ['id' => $id]);
        }

        return null;
    }

    /** Fiche de lecture : droit « demandes » pour une demande, droit de la ressource pour les autres (contributions). */
    private function canShow(AdminResource $resource, object $entity): bool
    {
        return $entity instanceof ServiceRequest
            ? $this->security->isGranted(Permission::REQUEST_VIEW, $entity)
            : $this->security->isGranted(Permission::RESOURCE_VIEW, $resource);
    }

    private function canOpen(AdminResource $resource, object $entity): bool
    {
        if (!$entity instanceof PublishableInterface) {
            return $this->security->isGranted(Permission::USER_MANAGE);
        }

        return $this->security->isGranted(Permission::CONTENT_EDIT, $entity) || $this->security->isGranted(Permission::CONTENT_TRANSLATE, $entity);
    }

    /**
     * @return list<Action>
     */
    private function actions(AdminResource $resource, object $entity, ?int $id): array
    {
        if (null === $id) {
            return [];
        }

        $actions = [];
        if (null !== $resource->showRoute && $this->canShow($resource, $entity)) {
            $actions[] = ['name' => 'show', 'label' => 'admin.action.open', 'method' => 'get', 'url' => $this->urlGenerator->generate($resource->showRoute, ['id' => $id]), 'token' => '', 'confirm' => false, 'danger' => false];
        }
        if (null !== $resource->editRoute && $this->canOpen($resource, $entity)) {
            $translateOnly = $entity instanceof PublishableInterface && !$this->security->isGranted(Permission::CONTENT_EDIT, $entity);
            $actions[] = ['name' => 'edit', 'label' => $translateOnly ? 'admin.action.translate' : 'admin.action.edit', 'method' => 'get', 'url' => $this->urlGenerator->generate($resource->editRoute, ['id' => $id]), 'token' => '', 'confirm' => false, 'danger' => false];
        }

        if ($resource->workflow && $entity instanceof PublishableInterface) {
            foreach ($this->transitions->available($entity, false) as $name) {
                $actions[] = $this->post($resource, $id, $name, 'admin.action.'.$name, false, false);
            }
            if ($this->security->isGranted(Permission::CONTENT_DELETE, $entity)) {
                $actions[] = $this->post($resource, $id, 'delete', 'admin.action.delete', true, true);
            }
        }

        return $actions;
    }

    /**
     * @return Action
     */
    private function post(AdminResource $resource, int $id, string $name, string $label, bool $confirm, bool $danger): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'method' => 'post',
            'url' => $this->urlGenerator->generate('app_admin_resource_action', ['resource' => $resource->id, 'id' => $id, 'transition' => $name]),
            'token' => $this->token($resource, (string) $id),
            'confirm' => $confirm,
            'danger' => $danger,
        ];
    }
}
