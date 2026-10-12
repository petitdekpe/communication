<?php

declare(strict_types=1);

namespace App\Twig;

use App\Admin\AdminMenu;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonctions Twig de l'administration : le menu latéral, déjà filtré par les Voters.
 */
final class AdminExtension
{
    public function __construct(private readonly AdminMenu $menu)
    {
    }

    /**
     * @return list<array{id: string, label: string, items: list<array{label: string, url: string, icon: string, active: bool}>}>
     */
    #[AsTwigFunction('admin_menu')]
    public function menu(): array
    {
        return $this->menu->groups();
    }
}
