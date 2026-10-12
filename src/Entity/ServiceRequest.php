<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Enum\RequestStatus;
use App\Repository\ServiceRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande déposée par un usager sur une démarche en ligne (accréditation presse, etc.).
 *
 * Les champs propres à chaque formulaire sont dans $payload (JSON) : le portail porte ainsi tous ses
 * formulaires sur une seule table. Le suivi se fait avec le couple numéro de suivi + e-mail, jamais par l'URL.
 * Données personnelles : durée de conservation paramétrable dans l'administration (purge par App\Retention\PersonalDataPurger).
 */
#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_service_request_tracking', columns: ['tracking_number'])]
class ServiceRequest implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 24)]
    private string $trackingNumber;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Service $service;

    /** Adresse de l'usager, en minuscules (clé de la consultation du suivi, destinataire de l'accusé). */
    #[ORM\Column(length: 180)]
    private string $email;

    /** Langue de l'usager : celle des e-mails qui lui sont adressés. */
    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(length: 16, enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Received;

    /**
     * Réponses au formulaire (hors e-mail et pièces jointes).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /** Commentaire interne du service presse : jamais communiqué à l'usager. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $internalNote = null;

    /** Moment où l'usager a donné son consentement (traitement de ses données, APDP). */
    #[ORM\Column]
    private \DateTimeImmutable $consentedAt;

    /** @var Collection<int, ServiceRequestAttachment> */
    #[ORM\OneToMany(targetEntity: ServiceRequestAttachment::class, mappedBy: 'request', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attachments;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $trackingNumber,
        Service $service,
        string $email,
        string $locale,
        array $payload,
        \DateTimeImmutable $consentedAt,
    ) {
        $this->trackingNumber = $trackingNumber;
        $this->service = $service;
        $this->email = mb_strtolower(trim($email));
        $this->locale = $locale;
        $this->payload = $payload;
        $this->consentedAt = $consentedAt;
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function setStatus(RequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $internalNote): static
    {
        $this->internalNote = null === $internalNote || '' === trim($internalNote) ? null : trim($internalNote);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
    }

    /**
     * @return Collection<int, ServiceRequestAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(ServiceRequestAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }
}
