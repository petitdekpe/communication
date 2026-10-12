<?php

declare(strict_types=1);

namespace App\Repository;

use App\Audit\AuditFilters;
use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function queryBuilder(AuditFilters $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC');

        if (null !== $filters->actor) {
            $qb->andWhere('a.actor LIKE :actor ESCAPE \'!\'')->setParameter('actor', '%'.strtr($filters->actor, ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
        }
        if (null !== $filters->action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $filters->action);
        }
        if (null !== $filters->entity) {
            $qb->andWhere('a.entityClass = :entity')->setParameter('entity', $filters->entity);
        }
        if (null !== $filters->from) {
            $qb->andWhere('a.occurredAt >= :from')->setParameter('from', $filters->from);
        }
        if (null !== $filters->to) {
            $qb->andWhere('a.occurredAt < :to')->setParameter('to', $filters->to->modify('+1 day'));
        }

        return $qb;
    }

    /**
     * @return list<string>
     */
    public function entityClasses(): array
    {
        /** @var list<array{entityClass: string}> $rows */
        $rows = $this->createQueryBuilder('a')->select('DISTINCT a.entityClass AS entityClass')->orderBy('a.entityClass')->getQuery()->getArrayResult();

        return array_column($rows, 'entityClass');
    }

    /**
     * Dernières publications (tableau de bord).
     *
     * @return list<AuditLog>
     */
    public function latestPublications(int $limit): array
    {
        /** @var list<AuditLog> $logs */
        $logs = $this->createQueryBuilder('a')
            ->andWhere('a.action = :publish')
            ->setParameter('publish', 'publish')
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $logs;
    }
}
