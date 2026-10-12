<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Enum\PublicationStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Statut de publication + date de publication + horodatage et auteurs (TimestampableTrait).
 * publishedAt est fixé automatiquement au premier passage en « published » s'il est vide.
 */
trait PublishableTrait
{
    use TimestampableTrait;

    #[ORM\Column(length: 16, enumType: PublicationStatus::class)]
    private PublicationStatus $status = PublicationStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** Publication programmée : validée par un relecteur, déclenchée par le Scheduler à cette date. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getStatus(): PublicationStatus
    {
        return $this->status;
    }

    public function setStatus(PublicationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(?\DateTimeImmutable $now = null): bool
    {
        return PublicationStatus::Published === $this->status
            && null !== $this->publishedAt
            && $this->publishedAt <= ($now ?? new \DateTimeImmutable());
    }
}
