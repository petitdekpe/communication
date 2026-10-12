<?php

declare(strict_types=1);

namespace App\Entity\Contract;

/**
 * Dates et auteurs de création / modification, renseignés automatiquement (App\Doctrine\AuditListener).
 */
interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): static;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static;

    public function getCreatedBy(): ?string;

    public function setCreatedBy(?string $createdBy): static;

    public function getUpdatedBy(): ?string;

    public function setUpdatedBy(?string $updatedBy): static;
}
