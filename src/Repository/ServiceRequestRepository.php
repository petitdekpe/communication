<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ServiceRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequest>
 */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    /**
     * Le numéro seul ne suffit pas : l'e-mail de la demande doit correspondre (casse ignorée).
     */
    public function findForTracking(string $trackingNumber, string $email): ?ServiceRequest
    {
        return $this->findOneBy([
            'trackingNumber' => strtoupper(trim($trackingNumber)),
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    public function trackingNumberExists(string $trackingNumber): bool
    {
        return null !== $this->findOneBy(['trackingNumber' => $trackingNumber]);
    }
}
