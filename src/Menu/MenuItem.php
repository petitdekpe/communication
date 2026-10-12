<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * Entrée de menu prête à afficher : le libellé est une clé de traduction, l'URL est déjà résolue
 * pour la langue courante. Une entrée sans URL est un simple groupe (ex. colonne du pied de page).
 */
final readonly class MenuItem
{
    /**
     * @param list<MenuItem> $children
     */
    public function __construct(
        public string $label,
        public ?string $href = null,
        public bool $external = false,
        public ?string $icon = null,
        public array $children = [],
    ) {
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    /**
     * Vrai si l'entrée (ou l'un de ses enfants) correspond exactement au chemin courant.
     */
    public function isCurrent(string $pathInfo): bool
    {
        if (!$this->external && $this->href === $pathInfo) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isCurrent($pathInfo)) {
                return true;
            }
        }

        return false;
    }
}
