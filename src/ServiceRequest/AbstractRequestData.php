<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs communs à tous les formulaires de démarche : e-mail, pièces jointes, consentement et piège à robots.
 */
abstract class AbstractRequestData implements RequestData
{
    #[Assert\NotBlank(message: 'request.error.email_required', normalizer: 'trim')]
    #[Assert\Email(message: 'request.error.email_invalid')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** @var array<int, UploadedFile> */
    #[Assert\Count(min: 1, max: AllowedAttachment::MAX_FILES, minMessage: 'request.error.attachments_required', maxMessage: 'request.error.attachments_max')]
    #[Assert\All([
        new Assert\File(
            maxSize: AllowedAttachment::MAX_SIZE,
            mimeTypes: ['application/pdf', 'image/jpeg'],
            extensions: AllowedAttachment::EXTENSIONS,
            maxSizeMessage: 'request.error.file_too_large',
            mimeTypesMessage: 'request.error.file_type',
            extensionsMessage: 'request.error.file_type',
        ),
    ])]
    public array $attachments = [];

    #[Assert\IsTrue(message: 'request.error.consent_required')]
    public bool $consent = false;

    /** Piège à robots : champ invisible pour les humains, donc toujours vide quand c'est un humain qui écrit. */
    #[Assert\Blank(message: 'request.error.rejected')]
    public ?string $website = null;

    public function getEmail(): string
    {
        return trim((string) $this->email);
    }

    public function getAttachments(): array
    {
        return array_values($this->attachments);
    }
}
