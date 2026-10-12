<?php

declare(strict_types=1);

namespace App\ServiceRequest;

/**
 * Pièces jointes acceptées : PDF et JPEG, 5 Mo au plus. Le type est toujours celui détecté sur le contenu
 * du fichier, jamais le type ni l'extension annoncés par le navigateur.
 */
final class AllowedAttachment
{
    public const int MAX_FILES = 5;
    public const string MAX_SIZE = '5M';
    public const int MAX_BYTES = 5 * 1024 * 1024;

    /** Type MIME détecté => extension de stockage. */
    public const array MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
    ];

    public const array EXTENSIONS = ['pdf', 'jpg', 'jpeg'];

    private function __construct()
    {
    }
}
