<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Service;
use App\Enum\Audience;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Seules les démarches publiées (date de publication passée) sont publiques.
 *
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Démarches publiées, groupées par ordre d'affichage puis par date ; filtrables par public.
     *
     * @return list<Service>
     */
    public function findPublished(\DateTimeImmutable $now, ?Audience $audience = null): array
    {
        $qb = $this->published($now)
            ->addSelect('translations')
            ->leftJoin('s.translations', 'translations')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC');

        if (null !== $audience) {
            $qb->andWhere('s.audience = :audience')->setParameter('audience', $audience);
        }

        /** @var list<Service> $services */
        $services = $qb->getQuery()->getResult();

        return $services;
    }

    /**
     * Services prioritaires de l'accueil.
     *
     * @return list<Service>
     */
    public function findFeatured(\DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Service> $services */
        $services = $this->published($now)
            // Pas de jointure sur les traductions : la limite porte sur les services, pas sur les lignes jointes.
            ->andWhere('s.featured = true')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $services;
    }

    /**
     * Le slug de la langue demandée est cherché d'abord, puis le slug français (repli).
     */
    public function findPublishedBySlug(string $slug, string $locale, \DateTimeImmutable $now): ?Service
    {
        foreach (array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]) as $candidate) {
            $service = $this->published($now)
                ->innerJoin('s.translations', 'match')
                ->andWhere('match.locale = :locale')
                ->andWhere('match.slug = :slug')
                ->setParameter('locale', $candidate)
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();

            if ($service instanceof Service) {
                return $service;
            }
        }

        return null;
    }

    private function published(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :published')
            ->andWhere('s.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now);
    }
}
