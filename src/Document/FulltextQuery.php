<?php

declare(strict_types=1);

namespace App\Document;

/**
 * Transforme une saisie libre en requête FULLTEXT booléenne de MariaDB : chaque mot est obligatoire
 * (« +mot* », recherche par préfixe). Les opérateurs et la ponctuation saisis sont retirés : la syntaxe
 * booléenne n'est jamais exposée à l'utilisateur. Les mots de moins de trois lettres sont ignorés, car
 * InnoDB n'indexe pas les mots plus courts (innodb_ft_min_token_size).
 */
final class FulltextQuery
{
    public const int MIN_LENGTH = 3;
    public const int MAX_TERMS = 8;

    /**
     * @return string|null null si la saisie ne contient aucun mot exploitable
     */
    public static function booleanMode(string $input): ?string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $input, $matches);

        $terms = [];
        foreach ($matches[0] as $word) {
            if (mb_strlen($word) >= self::MIN_LENGTH) {
                $terms[] = '+'.$word.'*';
            }
        }

        return [] === $terms ? null : implode(' ', \array_slice($terms, 0, self::MAX_TERMS));
    }
}
