<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Enum\DocumentType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie du formulaire d'un document officiel : le fichier est obligatoire à la création.
 */
final class DocumentData
{
    public const array MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    public DocumentType $type = DocumentType::Report;

    #[Assert\Length(max: 100)]
    public ?string $reference = null;

    public ?\DateTimeImmutable $documentDate = null;

    #[Assert\Choice(choices: ['fr', 'en'])]
    public string $fileLanguage = 'fr';

    #[Assert\File(maxSize: '20M', mimeTypes: self::MIME_TYPES, mimeTypesMessage: 'admin.error.document_type')]
    public ?UploadedFile $file = null;

    /** Vrai tant que le document n'a pas de fichier : l'envoi est alors obligatoire. */
    public bool $fileRequired = true;

    /** @var array<string, array<string, string|null>> */
    public array $translations = [];

    /** @var list<string> */
    public array $editable = ['fr', 'en'];

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->fileRequired && null === $this->file) {
            $context->buildViolation('admin.error.file_required')->atPath('file')->addViolation();
        }
        foreach ($this->editable as $locale) {
            $values = $this->translations[$locale] ?? [];
            $title = trim((string) ($values['title'] ?? ''));
            if ('' === $title && ('fr' === $locale || '' !== trim((string) ($values['summary'] ?? '')))) {
                $context->buildViolation('request.error.required')->atPath(\sprintf('translations[%s][title]', $locale))->addViolation();
            }
        }
    }
}
