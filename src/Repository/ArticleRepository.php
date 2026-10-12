<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Contract\TranslatableInterface;
use App\Enum\ArticleType;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Seuls les articles publiés dont la date de publication est passée sont lisibles par le public :
 * toutes les requêtes publiques passent par published().
 *
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Article publié dont le slug correspond, dans la langue demandée ou, à défaut, en français
     * (repli : un article non traduit reste accessible sous /en/… avec son slug français).
     */
    public function findPublishedBySlug(string $slug, string $locale, \DateTimeImmutable $now): ?Article
    {
        $locales = array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]);

        foreach ($locales as $candidate) {
            $article = $this->published($now)
                ->innerJoin('a.translations', 'match')
                ->andWhere('match.locale = :locale')
                ->andWhere('match.slug = :slug')
                ->setParameter('locale', $candidate)
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();

            if ($article instanceof Article) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Liste paginée, du plus récent au plus ancien.
     *
     * @return Paginator<Article>
     */
    public function paginate(ArticleFilters $filters, int $page, int $perPage, \DateTimeImmutable $now): Paginator
    {
        $qb = $this->published($now)
            ->addSelect('translations')
            ->leftJoin('a.translations', 'translations')
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $filters->type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $filters->type);
        }
        if (null !== $filters->categoryId) {
            $qb->andWhere('a.category = :category')->setParameter('category', $filters->categoryId);
        }
        if (null !== $filters->year) {
            $qb->andWhere('a.publishedAt >= :from AND a.publishedAt < :to')
                ->setParameter('from', new \DateTimeImmutable(\sprintf('%d-01-01 00:00:00', $filters->year)))
                ->setParameter('to', new \DateTimeImmutable(\sprintf('%d-01-01 00:00:00', $filters->year + 1)));
        }

        return new Paginator($qb, fetchJoinCollection: true);
    }

    /**
     * Années pour lesquelles au moins un article du type donné est publié (plus récente d'abord).
     *
     * @return list<int>
     */
    public function publishedYears(\DateTimeImmutable $now, ?ArticleType $type = null): array
    {
        $qb = $this->published($now)->select('MIN(a.publishedAt) AS first, MAX(a.publishedAt) AS last');
        if (null !== $type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }
        /** @var array{first: string|null, last: string|null} $range */
        $range = $qb->getQuery()->getSingleResult();
        if (null === $range['first'] || null === $range['last']) {
            return [];
        }

        return range((int) substr($range['last'], 0, 4), (int) substr($range['first'], 0, 4));
    }

    /**
     * Article « à la une » : le plus récent marqué comme tel, sinon le plus récent article non communiqué.
     */
    public function findFeatured(\DateTimeImmutable $now): ?Article
    {
        $featured = $this->latestQuery($now, 1)->andWhere('a.featured = true')->getQuery()->getOneOrNullResult();
        if ($featured instanceof Article) {
            return $featured;
        }

        $fallback = $this->latestQuery($now, 1)->andWhere('a.type <> :communique')
            ->setParameter('communique', ArticleType::Communique)
            ->getQuery()->getOneOrNullResult();

        return $fallback instanceof Article ? $fallback : null;
    }

    /**
     * @return list<Article>
     */
    public function findLatestOfType(ArticleType $type, int $limit, \DateTimeImmutable $now, ?int $categoryId = null): array
    {
        $qb = $this->latestQuery($now, $limit)->andWhere('a.type = :type')->setParameter('type', $type);
        if (null !== $categoryId) {
            $qb->andWhere('IDENTITY(a.category) = :category')->setParameter('category', $categoryId);
        }

        /* @var list<Article> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Dernières actualités hors communiqués, sans l'article donné (bloc « Actualités » de l'accueil).
     *
     * @return list<Article>
     */
    public function findLatestNews(int $limit, \DateTimeImmutable $now, ?Article $except = null, ?int $categoryId = null): array
    {
        $qb = $this->latestQuery($now, $limit)
            ->andWhere('a.type <> :communique')
            ->setParameter('communique', ArticleType::Communique);
        if (null !== $except) {
            $qb->andWhere('a != :except')->setParameter('except', $except);
        }

        if (null !== $categoryId) {
            $qb->andWhere('IDENTITY(a.category) = :category')->setParameter('category', $categoryId);
        }

        /* @var list<Article> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Articles liés : ceux qui partagent le plus de mots-clés avec l'article donné.
     *
     * @return list<Article>
     */
    public function findRelated(Article $article, int $limit, \DateTimeImmutable $now): array
    {
        if ($article->getTags()->isEmpty()) {
            return [];
        }

        /* @var list<Article> */
        return $this->published($now)
            ->innerJoin('a.tags', 'tag')
            ->andWhere('tag IN (:tags)')
            ->andWhere('a != :article')
            ->setParameter('tags', $article->getTags())
            ->setParameter('article', $article)
            ->groupBy('a.id')
            ->orderBy('COUNT(tag.id)', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Article>
     */
    public function findForFeed(int $limit, \DateTimeImmutable $now): array
    {
        /* @var list<Article> */
        return $this->latestQuery($now, $limit)->getQuery()->getResult();
    }

    private function latestQuery(\DateTimeImmutable $now, int $limit): QueryBuilder
    {
        return $this->published($now)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit);
    }

    private function published(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :published')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now);
    }
}
