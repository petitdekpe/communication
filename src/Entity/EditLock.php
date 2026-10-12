<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Verrou d'édition d'un contenu : il expire après 15 minutes sans activité (voir App\Lock\EditLockManager).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_edit_lock_subject', columns: ['subject_class', 'subject_id'])]
class EditLock
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
        #[ORM\Column(length: 180)]
        private string $holder,
        #[ORM\Column(length: 180)]
        private string $holderName,
        #[ORM\Column]
        private \DateTimeImmutable $lastActivityAt,
    ) {
    }

    public function getHolder(): string
    {
        return $this->holder;
    }

    public function getHolderName(): string
    {
        return $this->holderName;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function take(string $holder, string $holderName, \DateTimeImmutable $now): void
    {
        $this->holder = $holder;
        $this->holderName = $holderName;
        $this->lastActivityAt = $now;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->lastActivityAt = $now;
    }
}
