<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Un résultat de recherche : titre, adresse, résumé éventuel, date éventuelle, langue réelle du texte affiché.
 */
final class SearchResult
{
    public function __construct(
        public readonly SearchType $type,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $snippet,
        public readonly ?\DateTimeImmutable $date,
        public readonly string $locale,
    ) {
    }
}
