<?php

declare(strict_types=1);

namespace App\Document;

use App\Entity\OfficialDocument;
use App\Enum\DocumentType;

/**
 * Résultat d'une page de la bibliothèque : documents, pagination et filtres actifs (pour le formulaire).
 */
final readonly class DocumentListing
{
    /**
     * @param list<OfficialDocument> $documents
     * @param array<string, mixed>   $params    filtres actifs sous forme de paramètres d'URL (partageables)
     */
    public function __construct(
        public array $documents,
        public int $page,
        public int $totalPages,
        public int $total,
        public ?DocumentType $type,
        public string $keywords,
        public ?string $from,
        public ?string $to,
        public bool $newestFirst,
        public bool $keywordsIgnored,
        public array $params,
    ) {
    }

    /** Page demandée au-delà de la dernière (la page 1 d'une liste vide est valide). */
    public function isOutOfRange(): bool
    {
        return $this->page > max(1, $this->totalPages);
    }

    public function hasFilters(): bool
    {
        return [] !== array_diff_key($this->params, ['sort' => true]);
    }
}
