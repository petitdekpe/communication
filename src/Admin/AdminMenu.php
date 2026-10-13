<?php

declare(strict_types=1);

namespace App\Admin;

use App\Security\Permission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Menu latéral de l'administration, filtré par les Voters : une entrée n'apparaît que si l'utilisateur peut
 * ouvrir la page correspondante. Les templates ne font que parcourir le résultat.
 *
 * @phpstan-type MenuItem array{label: string, url: string, icon: string, active: bool}
 * @phpstan-type MenuGroup array{id: string, label: string, items: list<MenuItem>}
 */
final class AdminMenu
{
    /** Ressources dont l'entrée du menu ouvre un écran dédié plutôt que la liste générique. */
    private const array EDITORS = ['settings' => 'app_admin_settings'];

    public function __construct(
        private readonly AdminResources $resources,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<MenuGroup>
     */
    public function groups(): array
    {
        $path = $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '';
        $byGroup = array_fill_keys(AdminResources::GROUPS, []);

        foreach ($this->resources->all() as $resource) {
            if (!$this->security->isGranted(Permission::RESOURCE_VIEW, $resource)) {
                continue;
            }
            // Les paramètres ont leur propre écran d'édition (formulaire) à la place de la liste.
            $url = isset(self::EDITORS[$resource->id])
                ? $this->urlGenerator->generate(self::EDITORS[$resource->id])
                : $this->urlGenerator->generate('app_admin_resource_list', ['resource' => $resource->id]);
            $byGroup[$resource->group][] = ['label' => $resource->label, 'url' => $url, 'icon' => $resource->icon, 'active' => str_starts_with($path, $url)];
        }

        if ($this->security->isGranted(Permission::HOME_MANAGE)) {
            $url = $this->urlGenerator->generate('app_admin_home');
            $byGroup['structure'][] = ['label' => 'admin.resource.home', 'url' => $url, 'icon' => 'ri:home-4-line', 'active' => str_starts_with($path, $url)];
        }

        if ($this->security->isGranted(Permission::TRANSLATION_MANAGE)) {
            $url = $this->urlGenerator->generate('app_admin_translations');
            $byGroup['structure'][] = ['label' => 'admin.resource.translations', 'url' => $url, 'icon' => 'ri:translate-2', 'active' => str_starts_with($path, $url)];
        }

        if ($this->security->isGranted(Permission::AUDIT_VIEW)) {
            $url = $this->urlGenerator->generate('app_admin_audit');
            $byGroup['users'][] = ['label' => 'admin.resource.audit', 'url' => $url, 'icon' => 'ri:history-line', 'active' => str_starts_with($path, $url)];
        }

        $groups = [];
        foreach ($byGroup as $id => $items) {
            if ([] !== $items) {
                $groups[] = ['id' => $id, 'label' => 'admin.group.'.$id, 'items' => $items];
            }
        }

        return $groups;
    }
}
