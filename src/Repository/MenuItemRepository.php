<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * Toutes les entrées d'un menu (tous niveaux), traductions comprises, dans l'ordre d'affichage.
     *
     * @return list<MenuItem>
     */
    public function findForMenu(string $menu): array
    {
        /** @var list<MenuItem> $items */
        $items = $this->createQueryBuilder('m')
            ->addSelect('t')
            ->leftJoin('m.translations', 't')
            ->andWhere('m.menu = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }
}
