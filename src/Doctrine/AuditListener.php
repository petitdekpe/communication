<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TimestampableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Renseigne createdAt / updatedAt / createdBy / updatedBy et, au passage en « published »,
 * publishedAt s'il est vide. La modification d'une traduction met à jour son entité traduisible.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final class AuditListener
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $now = $this->clock->now();
        $user = $this->currentUser();

        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt($now);
        }
        $entity->setCreatedBy($entity->getCreatedBy() ?? $user);
        $entity->setUpdatedAt($entity->getUpdatedAt() ?? $now);
        $entity->setUpdatedBy($entity->getUpdatedBy() ?? $user);

        if ($entity instanceof PublishableInterface) {
            $this->stampPublication($entity, $now);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // Entités existantes modifiées directement ou via l'une de leurs traductions.
        $touched = [];
        foreach ([...$uow->getScheduledEntityUpdates(), ...$uow->getScheduledEntityInsertions()] as $entity) {
            $target = $entity instanceof TranslationInterface ? $entity->getTranslatable() : $entity;
            if ($target instanceof TimestampableInterface && !$uow->isScheduledForInsert($target)) {
                $touched[spl_object_id($target)] = $target;
            }
        }

        $now = $this->clock->now();
        foreach ($touched as $entity) {
            $entity->setUpdatedAt($now);
            $entity->setUpdatedBy($this->currentUser() ?? $entity->getUpdatedBy());
            if ($entity instanceof PublishableInterface) {
                $this->stampPublication($entity, $now);
            }

            $metadata = $em->getClassMetadata($entity::class);
            if ($uow->isScheduledForUpdate($entity)) {
                $uow->recomputeSingleEntityChangeSet($metadata, $entity);
            } else {
                $uow->computeChangeSet($metadata, $entity);
            }
        }
    }

    private function stampPublication(PublishableInterface $entity, \DateTimeImmutable $now): void
    {
        if (PublicationStatus::Published === $entity->getStatus() && null === $entity->getPublishedAt()) {
            $entity->setPublishedAt($now);
        }
    }

    private function currentUser(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
