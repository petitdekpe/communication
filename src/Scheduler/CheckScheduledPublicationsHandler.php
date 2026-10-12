<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Admin\AdminResources;
use App\Enum\PublicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Pour chaque contenu en relecture dont la date programmée est échue, met un message PublishContent en file.
 */
#[AsMessageHandler]
final class CheckScheduledPublicationsHandler
{
    public function __construct(
        private readonly AdminResources $resources,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CheckScheduledPublications $message): int
    {
        $dispatched = 0;
        foreach ($this->resources->all() as $resource) {
            if (!$resource->workflow) {
                continue;
            }
            /** @var list<array{id: int}> $due */
            $due = $this->entityManager->createQueryBuilder()
                ->select('e.id')
                ->from($resource->entity, 'e')
                ->where('e.status = :status')
                ->andWhere('e.scheduledAt IS NOT NULL')
                ->andWhere('e.scheduledAt <= :now')
                ->setParameter('status', PublicationStatus::InReview)
                ->setParameter('now', $this->clock->now())
                ->getQuery()
                ->getArrayResult();

            foreach ($due as $row) {
                $this->bus->dispatch(new PublishContent($resource->entity, $row['id']));
                ++$dispatched;
            }
        }

        return $dispatched;
    }
}
