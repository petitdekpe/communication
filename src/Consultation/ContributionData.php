<?php

declare(strict_types=1);

namespace App\Consultation;

use App\ServiceRequest\AllowedAttachment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie du formulaire de contribution : nom, organisation, texte, fichier optionnel (PDF ou JPG, 5 Mo).
 */
final class ContributionData
{
    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 150)]
    public ?string $name = null;

    #[Assert\Length(max: 200)]
    public ?string $organization = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(min: 10, max: 10000, minMessage: 'consultation.error.text_short', maxMessage: 'consultation.error.text_long')]
    public ?string $text = null;

    #[Assert\File(
        maxSize: AllowedAttachment::MAX_SIZE,
        mimeTypes: ['application/pdf', 'image/jpeg'],
        extensions: AllowedAttachment::EXTENSIONS,
        maxSizeMessage: 'request.error.file_too_large',
        mimeTypesMessage: 'request.error.file_type',
        extensionsMessage: 'request.error.file_type',
    )]
    public ?UploadedFile $file = null;

    #[Assert\IsTrue(message: 'request.error.consent_required')]
    public bool $consent = false;

    /** Piège à robots (voir App\Form\HoneypotType). */
    #[Assert\Blank(message: 'request.error.rejected')]
    public ?string $website = null;
}
