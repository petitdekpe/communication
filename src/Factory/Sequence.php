<?php

declare(strict_types=1);

namespace App\Factory;

/**
 * Numéro croissant qui rend uniques les textes des fixtures (« [À VALIDER] Titre de l'article 12 »).
 * Hors des factories : celles de Foundry sont immuables.
 */
final class Sequence
{
    private static int $current = 0;

    public static function next(): int
    {
        return ++self::$current;
    }
}
