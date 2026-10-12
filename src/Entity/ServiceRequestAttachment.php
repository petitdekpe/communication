<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Pièce jointe d'une demande. Le fichier vit dans le stockage privé (hors racine web) sous une clé
 * aléatoire ; le nom d'origine n'est conservé qu'ici, à titre d'information.
 */
#[ORM\Entity]
class ServiceRequestAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServiceRequest $request;

    /** Clé du fichier dans le stockage privé. */
    #[ORM\Column(length: 255, unique: true)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    /** Type MIME détecté sur le contenu du fichier (jamais celui annoncé par le navigateur). */
    #[ORM\Column(length: 127)]
    private string $mimeType;

    /** Poids en octets. */
    #[ORM\Column]
    private int $size;

    public function __construct(ServiceRequest $request, string $path, string $originalFilename, string $mimeType, int $size)
    {
        $this->request = $request;
        $this->path = $path;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $request->addAttachment($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequest(): ServiceRequest
    {
        return $this->request;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
