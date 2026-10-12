<?php

declare(strict_types=1);

namespace App\News;

use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArticleType;

/**
 * Résultat d'une page de liste : articles, pagination, filtres actifs et choix proposés.
 */
final readonly class NewsListing
{
    /**
     * @param list<Article>        $articles
     * @param list<int>            $years      années proposées par le filtre
     * @param list<Category>       $categories rubriques proposées par le filtre
     * @param array<string, mixed> $params     filtres actifs sous forme de paramètres d'URL (partageables)
     */
    public function __construct(
        public array $articles,
        public int $page,
        public int $totalPages,
        public int $total,
        public ?ArticleType $type,
        public ?string $category,
        public ?int $year,
        public array $years,
        public array $categories,
        public array $params,
    ) {
    }

    /** Page demandée au-delà de la dernière (la page 1 d'une liste vide est valide). */
    public function isOutOfRange(): bool
    {
        return $this->page > max(1, $this->totalPages);
    }

    public function hasFilters(): bool
    {
        return [] !== $this->params;
    }
}
