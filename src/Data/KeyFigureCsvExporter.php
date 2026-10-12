<?php

declare(strict_types=1);

namespace App\Data;

use App\Entity\KeyFigure;

/**
 * Export CSV des chiffres clés (UTF-8 avec BOM pour Excel, séparateur « ; » adapté aux tableurs francophones,
 * nombres avec point décimal). Les textes sont neutralisés contre l'injection de formules (=, +, -, @).
 */
final class KeyFigureCsvExporter
{
    /**
     * @param iterable<KeyFigure> $figures
     * @param resource            $output
     */
    public function write(iterable $figures, string $locale, $output): void
    {
        fwrite($output, "\xEF\xBB\xBF");
        $this->row($output, ['label', 'value', 'unit', 'source', 'source_url', 'data_date', 'language']);

        foreach ($figures as $figure) {
            $translation = $figure->translate($locale);
            $this->row($output, [
                self::safe((string) $translation->getLabel()),
                null === $figure->getValue() ? '' : self::number($figure->getValue()),
                self::safe((string) $translation->getUnit()),
                self::safe((string) $translation->getSource()),
                self::safe((string) $figure->getSourceUrl()),
                $figure->getDataDate()?->format('Y-m-d') ?? '',
                $translation->getLocale(),
            ]);
        }
    }

    /**
     * « 12.5000 » => « 12.5 » ; « 3.0000 » => « 3 ».
     */
    public static function number(string $decimal): string
    {
        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }

    /**
     * Préfixe d'une apostrophe tout texte qu'un tableur interpréterait comme une formule.
     */
    public static function safe(string $text): string
    {
        return '' !== $text && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }

    /**
     * @param resource          $output
     * @param list<string|null> $fields
     */
    private function row($output, array $fields): void
    {
        fputcsv($output, $fields, ';', '"', '\\', "\r\n");
    }
}
