<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique du circuit de publication : qui a appliqué quelle transition, quand, avec quel motif.
 * Écrit uniquement par App\Workflow\PublicationHistoryListener ; jamais modifié.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_publication_history_subject', columns: ['subject_class', 'subject_id'])]
class PublicationHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private readonly string $subjectClass,
        #[ORM\Column]
        private readonly int $subjectId,
        /** Nom de la transition (submit, publish…) ou « schedule » / « unschedule ». */
        #[ORM\Column(length: 24)]
        private readonly string $transition,
        #[ORM\Column(length: 16, nullable: true)]
        private readonly ?string $fromStatus,
        #[ORM\Column(length: 16, nullable: true)]
        private readonly ?string $toStatus,
        /** Adresse e-mail de l'auteur de l'action ; « system » pour le Scheduler. */
        #[ORM\Column(length: 180)]
        private readonly string $actor,
        #[ORM\Column(length: 180, nullable: true)]
        private readonly ?string $actorName,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $reason,
        #[ORM\Column]
        private readonly \DateTimeImmutable $occurredAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getFromStatus(): ?string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): ?string
    {
        return $this->toStatus;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
