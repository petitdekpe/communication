<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Page;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * Toutes les pages publiées (traductions comprises) à la date donnée, y compris la page système d'accueil.
     * Arborescence entière en une requête : fil d'Ariane, sous-pages, plan du site et résolution des chemins
     * se font ensuite en mémoire (PageTree).
     *
     * @return list<Page>
     */
    public function findAllPublished(\DateTimeImmutable $now): array
    {
        /** @var list<Page> $pages */
        $pages = $this->createQueryBuilder('p')
            ->addSelect('t')
            ->leftJoin('p.translations', 't')
            ->andWhere('p.status = :published')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $pages;
    }

    /** Page système (accueil…), publiée ou non. */
    public function findSystem(string $key): ?Page
    {
        return $this->findOneBy(['systemKey' => $key]);
    }
}
