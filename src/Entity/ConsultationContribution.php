<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\ConsultationContributionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contribution déposée par un citoyen à une consultation : nom, organisation, texte, fichier optionnel.
 * Données personnelles : durée de conservation paramétrable dans l'administration. Le fichier vit dans le stockage privé.
 */
#[ORM\Entity(repositoryClass: ConsultationContributionRepository::class)]
class ConsultationContribution implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Consultation $consultation;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $organization;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\Column]
    private \DateTimeImmutable $consentedAt;

    /** Clé du fichier joint dans le stockage privé. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileOriginalName = null;

    #[ORM\Column(length: 127, nullable: true)]
    private ?string $fileMimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    public function __construct(Consultation $consultation, string $name, ?string $organization, string $text, \DateTimeImmutable $consentedAt)
    {
        $this->consultation = $consultation;
        $this->name = $name;
        $this->organization = $organization;
        $this->text = $text;
        $this->consentedAt = $consentedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultation(): Consultation
    {
        return $this->consultation;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getFileOriginalName(): ?string
    {
        return $this->fileOriginalName;
    }

    public function getFileMimeType(): ?string
    {
        return $this->fileMimeType;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function attachFile(string $path, string $originalName, string $mimeType, int $size): static
    {
        $this->filePath = $path;
        $this->fileOriginalName = $originalName;
        $this->fileMimeType = $mimeType;
        $this->fileSize = $size;

        return $this;
    }
}
