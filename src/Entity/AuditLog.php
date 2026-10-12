<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entrée du journal d'audit : qui (adresse e-mail, conservée même si le compte est supprimé), quoi, quand,
 * avec les valeurs avant/après de chaque champ modifié. Écrite uniquement par App\Audit\AuditTrailListener ;
 * jamais modifiée ni supprimée par l'application.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_class', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_occurred', columns: ['occurred_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    /** Adresse e-mail de l'auteur ; « system » pour une action sans utilisateur (console, formulaire public). */
    #[ORM\Column(length: 180)]
    private string $actor;

    /** create, update, delete, publish, unpublish, submit, archive. */
    #[ORM\Column(length: 24)]
    private string $action;

    /** Nom court de la classe (Article, OfficialDocument, User…). */
    #[ORM\Column(length: 80)]
    private string $entityClass;

    #[ORM\Column(nullable: true)]
    private ?int $entityId;

    /** Libellé lisible du contenu au moment de l'action (titre, e-mail…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label;

    /**
     * Champ => [avant, après]. Les secrets (mot de passe, secret TOTP) ne sont jamais enregistrés : « *** ».
     *
     * @var array<string, array{0: mixed, 1: mixed}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $changes;

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changes
     */
    public function __construct(\DateTimeImmutable $occurredAt, string $actor, string $action, string $entityClass, ?int $entityId, ?string $label, array $changes)
    {
        $this->occurredAt = $occurredAt;
        $this->actor = $actor;
        $this->action = $action;
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;
        $this->label = $label;
        $this->changes = $changes;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
