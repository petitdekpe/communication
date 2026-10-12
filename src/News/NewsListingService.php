<?php

declare(strict_types=1);

namespace App\News;

use App\Entity\Article;
use App\Enum\ArticleType;
use App\Repository\ArticleFilters;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Psr\Clock\ClockInterface;

/**
 * Construit une page de liste à partir des paramètres d'URL (type, category, year, page).
 * Les valeurs invalides sont ignorées (type, année) ou ne donnent aucun résultat (rubrique inconnue) :
 * une URL partagée ne produit jamais d'erreur.
 */
final class NewsListingService
{
    public const int PER_PAGE = 12;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CategoryRepository $categories,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $query     paramètres d'URL
     * @param ArticleType|null     $fixedType type imposé par la page (ex. communiqués) : le filtre « type » n'existe plus
     */
    public function listing(array $query, string $locale, ?ArticleType $fixedType = null): NewsListing
    {
        $now = $this->clock->now();

        $type = $fixedType ?? ArticleType::tryFrom($this->string($query, 'type'));
        $categorySlug = '' !== $this->string($query, 'category') ? $this->string($query, 'category') : null;
        $year = $this->year($query);
        $page = max(1, (int) $this->string($query, 'page'));

        $categoryId = null;
        if (null !== $categorySlug) {
            // Rubrique inconnue : identifiant 0, donc aucun résultat.
            $categoryId = $this->categories->findBySlug($categorySlug, $locale)?->getId() ?? 0;
        }

        $paginator = $this->articles->paginate(new ArticleFilters($type, $categoryId, $year), $page, self::PER_PAGE, $now);
        $total = \count($paginator);

        $params = array_filter([
            'type' => null === $fixedType ? $type?->value : null,
            'category' => $categorySlug,
            'year' => $year,
        ], static fn (mixed $value): bool => null !== $value);

        /** @var list<Article> $articles */
        $articles = iterator_to_array($paginator, false);

        return new NewsListing(
            articles: $articles,
            page: $page,
            totalPages: (int) ceil($total / self::PER_PAGE),
            total: $total,
            type: $type,
            category: $categorySlug,
            year: $year,
            years: $this->articles->publishedYears($now, $fixedType),
            categories: $this->categories->findAllOrdered(),
            params: $params,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function string(array $query, string $key): string
    {
        $value = $query[$key] ?? '';

        return \is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function year(array $query): ?int
    {
        $year = $this->string($query, 'year');

        return 1 === preg_match('/^(19|20)\d{2}$/', $year) ? (int) $year : null;
    }
}
