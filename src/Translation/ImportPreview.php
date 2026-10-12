<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * Résultat de l'analyse d'un fichier XLIFF, avant toute écriture : lignes (valides ou en erreur) et erreurs
 * du fichier lui-même (clés de traduction).
 */
final readonly class ImportPreview
{
    /**
     * @param list<ImportRow> $rows
     * @param list<string>    $fileErrors
     */
    public function __construct(
        public array $rows = [],
        public array $fileErrors = [],
    ) {
    }

    /**
     * @return list<ImportRow>
     */
    public function applicable(): array
    {
        return array_values(array_filter($this->rows, static fn (ImportRow $row): bool => null === $row->error));
    }

    public function hasApplicable(): bool
    {
        return [] !== $this->applicable();
    }
}
