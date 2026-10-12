<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MediaGallery;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MediaGallery>
 */
class MediaGalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaGallery::class);
    }

    /**
     * Galeries publiées, les plus récentes d'abord, avec leurs images.
     *
     * @return list<MediaGallery>
     */
    public function findPublished(\DateTimeImmutable $now): array
    {
        /** @var list<MediaGallery> $galleries */
        $galleries = $this->createQueryBuilder('g')
            ->addSelect('t', 'i', 'm')
            ->leftJoin('g.translations', 't')
            ->leftJoin('g.items', 'i')
            ->leftJoin('i.media', 'm')
            ->andWhere('g.status = :published')
            ->andWhere('g.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('g.publishedAt', 'DESC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $galleries;
    }
}
