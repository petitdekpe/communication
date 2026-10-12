<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Description d'une ressource de l'administration (liste filtrable, triable, paginée).
 *
 * Chaque colonne : ['key' => …, 'label' => clé de traduction, 'path' => chemin PropertyAccess depuis la ligne,
 * 'type' => text|date|datetime|status|bool|enum|title, 'sort' => expression DQL (colonne triable) ou null].
 */
final class AdminResource
{
    /**
     * @param class-string                                                                            $entity
     * @param string                                                                                  $role                    rôle minimal pour voir la ressource (voir ContentVoter)
     * @param list<array{key: string, label: string, path: string, type: string, sort?: string|null}> $columns
     * @param list<string>                                                                            $searchFields            champs de l'entité parcourus par la recherche
     * @param list<string>                                                                            $translationSearchFields champs de la traduction parcourus par la recherche
     */
    public function __construct(
        public readonly string $id,
        public readonly string $group,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $entity,
        public readonly string $role,
        public readonly array $columns,
        public readonly array $searchFields = [],
        public readonly array $translationSearchFields = [],
        public readonly string $defaultSort = 'e.id',
        public readonly bool $workflow = false,
        public readonly ?string $newRoute = null,
        public readonly ?string $editRoute = null,
        public readonly ?string $showRoute = null,
        public readonly bool $translatable = false,
        public readonly bool $exportable = false,
    ) {
    }

    /** Classe de la traduction (ArticleTranslation…), pour la recherche de titre et la complétude. */
    public function translationClass(): ?string
    {
        return $this->translatable ? $this->entity.'Translation' : null;
    }
}
