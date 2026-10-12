<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Contract\TranslatableInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Rubrique dont le slug correspond dans la langue demandée ou, à défaut, en français
     * (un lien de filtre partagé en français reste valable sous /en).
     */
    public function findBySlug(string $slug, string $locale): ?Category
    {
        foreach (array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]) as $candidate) {
            $category = $this->createQueryBuilder('c')
                ->innerJoin('c.translations', 't')
                ->andWhere('t.locale = :locale AND t.slug = :slug')
                ->setParameter('locale', $candidate)
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();
            if ($category instanceof Category) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return list<Category>
     */
    public function findAllOrdered(): array
    {
        /* @var list<Category> */
        return $this->createQueryBuilder('c')->orderBy('c.position', 'ASC')->addOrderBy('c.id', 'ASC')->getQuery()->getResult();
    }
}
