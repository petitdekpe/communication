<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\Contract\TranslatableInterface;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consultation>
 */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /**
     * Consultations publiées : les plus récemment ouvertes d'abord.
     *
     * @return list<Consultation>
     */
    public function findPublished(\DateTimeImmutable $now): array
    {
        /** @var list<Consultation> $consultations */
        $consultations = $this->published($now)
            ->addSelect('translations')
            ->leftJoin('c.translations', 'translations')
            ->orderBy('c.opensAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $consultations;
    }

    /**
     * Consultation mise en avant sur l'accueil : la consultation ouverte qui ferme le plus tôt, à défaut la plus récente.
     */
    public function findHighlight(\DateTimeImmutable $now): ?Consultation
    {
        $open = $this->published($now)
            ->andWhere('c.opensAt <= :now')
            ->andWhere('c.closesAt > :now')
            ->orderBy('c.closesAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($open instanceof Consultation) {
            return $open;
        }

        $latest = $this->published($now)->orderBy('c.opensAt', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $latest instanceof Consultation ? $latest : null;
    }

    public function findPublishedBySlug(string $slug, string $locale, \DateTimeImmutable $now): ?Consultation
    {
        foreach (array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]) as $candidate) {
            $consultation = $this->published($now)
                ->innerJoin('c.translations', 'match')
                ->andWhere('match.locale = :locale')
                ->andWhere('match.slug = :slug')
                ->setParameter('locale', $candidate)
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();

            if ($consultation instanceof Consultation) {
                return $consultation;
            }
        }

        return null;
    }

    private function published(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :published')
            ->andWhere('c.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now);
    }
}
