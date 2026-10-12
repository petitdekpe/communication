<?php

declare(strict_types=1);

namespace App\Document;

use App\Enum\DocumentType;

/**
 * Filtres de la bibliothèque. $keywords est la saisie brute, $match sa traduction en requête FULLTEXT :
 * une saisie sans mot exploitable (« de ») donne $match null et aucun résultat, jamais toute la bibliothèque.
 */
final readonly class DocumentFilters
{
    public function __construct(
        public string $locale,
        public ?DocumentType $type = null,
        public ?string $keywords = null,
        public ?string $match = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public bool $newestFirst = true,
    ) {
    }

    /** Mots-clés saisis mais inexploitables : la recherche ne peut rien renvoyer. */
    public function matchesNothing(): bool
    {
        return null !== $this->keywords && null === $this->match;
    }
}
