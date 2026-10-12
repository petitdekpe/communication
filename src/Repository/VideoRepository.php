<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Video;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /**
     * @return list<Video>
     */
    public function findPublished(\DateTimeImmutable $now): array
    {
        /** @var list<Video> $videos */
        $videos = $this->createQueryBuilder('v')
            ->addSelect('t')
            ->leftJoin('v.translations', 't')
            ->andWhere('v.status = :published')
            ->andWhere('v.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('v.position', 'ASC')
            ->addOrderBy('v.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $videos;
    }
}
