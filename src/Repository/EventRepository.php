<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Event;
use App\Enum\EventType;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Seuls les événements publiés (date de publication passée) sont publics.
 * « À venir » : l'événement n'est pas terminé (fin, ou début s'il n'a pas de fin, dans le futur).
 *
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    /** Garde-fou du flux iCal global. */
    private const int FEED_LIMIT = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Liste paginée : à venir (le plus proche d'abord) ou passés (le plus récent d'abord).
     *
     * @return Paginator<Event>
     */
    public function paginate(bool $upcoming, ?EventType $type, int $page, int $perPage, \DateTimeImmutable $now): Paginator
    {
        $qb = $this->published($now)
            ->addSelect('translations')
            ->leftJoin('e.translations', 'translations')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($upcoming) {
            $qb->andWhere('COALESCE(e.endsAt, e.startsAt) >= :now')->orderBy('e.startsAt', 'ASC')->addOrderBy('e.id', 'ASC');
        } else {
            $qb->andWhere('COALESCE(e.endsAt, e.startsAt) < :now')->orderBy('e.startsAt', 'DESC')->addOrderBy('e.id', 'DESC');
        }
        if (null !== $type) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }

        return new Paginator($qb, fetchJoinCollection: true);
    }

    /**
     * Événements qui touchent l'intervalle [from, to[ (vue mois), du plus tôt au plus tard.
     *
     * @return list<Event>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, ?EventType $type, \DateTimeImmutable $now): array
    {
        $qb = $this->published($now)
            ->addSelect('translations')
            ->leftJoin('e.translations', 'translations')
            ->andWhere('e.startsAt < :to')
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.id', 'ASC');
        if (null !== $type) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }

        /** @var list<Event> $events */
        $events = $qb->getQuery()->getResult();

        return $events;
    }

    /**
     * Prochains événements (bloc « Agenda » de l'accueil).
     *
     * @return list<Event>
     */
    public function findUpcoming(int $limit, \DateTimeImmutable $now): array
    {
        return $this->limited($this->published($now)
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :now')
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->setMaxResults($limit));
    }

    /**
     * Événement publié dont le slug correspond, dans la langue demandée ou, à défaut, en français
     * (repli : un événement non traduit reste accessible sous /en avec son slug français).
     */
    public function findPublishedBySlug(string $slug, string $locale, \DateTimeImmutable $now): ?Event
    {
        foreach (array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]) as $candidate) {
            $event = $this->published($now)
                ->innerJoin('e.translations', 'match')
                ->andWhere('match.locale = :locale')
                ->andWhere('match.slug = :slug')
                ->setParameter('locale', $candidate)
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getOneOrNullResult();

            if ($event instanceof Event) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Flux iCal global : tous les événements publiés, du plus ancien au plus récent.
     *
     * @return list<Event>
     */
    public function findForFeed(\DateTimeImmutable $now): array
    {
        return array_reverse($this->limited($this->published($now)
            ->orderBy('e.startsAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(self::FEED_LIMIT)));
    }

    /**
     * Résultat limité avec ses traductions : le Paginator applique la limite aux événements, pas aux lignes jointes.
     *
     * @return list<Event>
     */
    private function limited(QueryBuilder $qb): array
    {
        $qb->addSelect('translations')->leftJoin('e.translations', 'translations');

        /** @var list<Event> $events */
        $events = iterator_to_array(new Paginator($qb, fetchJoinCollection: true), false);

        return $events;
    }

    private function published(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :published')
            ->andWhere('e.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now);
    }
}
