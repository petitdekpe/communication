<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Abonnés dont l'inscription est confirmée, par ordre de confirmation (export pour l'administration).
     *
     * @return iterable<NewsletterSubscriber>
     */
    public function findConfirmed(): iterable
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :confirmed')
            ->setParameter('confirmed', SubscriptionStatus::Confirmed)
            ->orderBy('s.confirmedAt', 'ASC')
            ->getQuery()
            ->toIterable();
    }
}
