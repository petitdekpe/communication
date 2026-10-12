<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConsultationContribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsultationContribution>
 */
class ConsultationContributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsultationContribution::class);
    }
}
