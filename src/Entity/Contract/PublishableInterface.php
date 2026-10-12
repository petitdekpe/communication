<?php

declare(strict_types=1);

namespace App\Entity\Contract;

use App\Enum\PublicationStatus;

interface PublishableInterface extends TimestampableInterface
{
    public function getStatus(): PublicationStatus;

    public function setStatus(PublicationStatus $status): static;

    public function getPublishedAt(): ?\DateTimeImmutable;

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static;

    public function getScheduledAt(): ?\DateTimeImmutable;

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static;

    /** Publié et dont la date de publication est passée. */
    public function isPublished(?\DateTimeImmutable $now = null): bool;
}
