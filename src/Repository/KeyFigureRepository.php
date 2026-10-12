<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KeyFigure;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KeyFigure>
 */
class KeyFigureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KeyFigure::class);
    }

    /**
     * Chiffres clés publiés, dans l'ordre d'affichage.
     *
     * @return list<KeyFigure>
     */
    public function findPublished(\DateTimeImmutable $now, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('k');
        if (null === $limit) {
            // Avec une limite, pas de jointure : la limite porte sur les chiffres, pas sur les lignes jointes.
            $qb->addSelect('t')->leftJoin('k.translations', 't');
        }

        /** @var list<KeyFigure> $figures */
        $figures = $qb
            ->andWhere('k.status = :published')
            ->andWhere('k.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('k.position', 'ASC')
            ->addOrderBy('k.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $figures;
    }
}
