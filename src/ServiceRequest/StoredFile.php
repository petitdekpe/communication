<?php

declare(strict_types=1);

namespace App\ServiceRequest;

/**
 * Fichier déposé dans le stockage privé : clé, nom d'origine (pour information), type détecté, poids.
 */
final class StoredFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
    ) {
    }
}
