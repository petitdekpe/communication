<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Instantané JSON d'un contenu à chaque enregistrement (voir App\Version\ContentSnapshotter).
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_content_version_subject', columns: ['subject_class', 'subject_id'])]
#[ORM\UniqueConstraint(name: 'uniq_content_version_number', columns: ['subject_class', 'subject_id', 'number'])]
class ContentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        #[ORM\Column(length: 255)]
        private readonly string $subjectClass,
        #[ORM\Column]
        private readonly int $subjectId,
        #[ORM\Column]
        private readonly int $number,
        #[ORM\Column(type: Types::JSON)]
        private readonly array $snapshot,
        #[ORM\Column(length: 180)]
        private readonly string $author,
        #[ORM\Column]
        private readonly \DateTimeImmutable $createdAt,
        /** Renseigné pour une restauration : numéro de la version rétablie. */
        #[ORM\Column(nullable: true)]
        private readonly ?int $restoredFrom = null,
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

    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRestoredFrom(): ?int
    {
        return $this->restoredFrom;
    }
}
