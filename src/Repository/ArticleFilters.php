<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\ArticleType;

/**
 * Filtres de la liste d'articles. categoryId vaut 0 pour une rubrique inconnue (aucun résultat).
 */
final readonly class ArticleFilters
{
    public function __construct(
        public ?ArticleType $type = null,
        public ?int $categoryId = null,
        public ?int $year = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->type && null === $this->categoryId && null === $this->year;
    }
}
