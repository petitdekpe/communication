<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * Une modification proposée par un fichier XLIFF : un champ anglais d'un contenu. Une ligne avec une erreur
 * (clé de traduction) est affichée dans l'aperçu mais jamais appliquée.
 */
final readonly class ImportRow
{
    public function __construct(
        public string $resourceId,
        public int $entityId,
        public string $title,
        public string $field,
        public ?string $current,
        public string $proposed,
        public ?string $error = null,
    ) {
    }

    public function withError(string $error): self
    {
        return new self($this->resourceId, $this->entityId, $this->title, $this->field, $this->current, $this->proposed, $error);
    }
}
