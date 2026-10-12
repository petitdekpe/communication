<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\MenuItem as MenuItemEntity;

/**
 * Entrée de l'arbre affiché dans l'éditeur : l'entité, son libellé (langue de travail, sinon français),
 * un résumé de sa destination et ses enfants.
 */
final readonly class MenuNode
{
    /**
     * @param list<MenuNode> $children
     */
    public function __construct(
        public MenuItemEntity $item,
        public string $label,
        public string $destination,
        public array $children = [],
    ) {
    }
}
