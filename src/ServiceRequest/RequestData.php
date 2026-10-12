<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Données saisies dans le formulaire d'une démarche en ligne.
 */
interface RequestData
{
    public function getEmail(): string;

    /**
     * @return list<UploadedFile>
     */
    public function getAttachments(): array;

    /**
     * Réponses propres à la démarche, enregistrées en JSON (l'e-mail et les pièces ont leur propre colonne).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
