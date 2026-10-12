<?php

declare(strict_types=1);

namespace App\Version;

/**
 * Différence mot à mot entre deux textes (plus longue sous-séquence commune). Les balises HTML sont des jetons à
 * part entière et restent affichées telles quelles (échappées par Twig) : le diff montre le contenu éditorial brut.
 *
 * @phpstan-type Op array{type: 'same'|'del'|'ins', text: string}
 */
final class TextDiffer
{
    /** Au-delà, la comparaison fine est trop coûteuse : le bloc modifié est montré en entier. */
    private const int MAX_CELLS = 4_000_000;

    /**
     * @return list<Op>
     */
    public function diff(string $old, string $new): array
    {
        $a = $this->tokenize($old);
        $b = $this->tokenize($new);

        $start = 0;
        $endA = \count($a);
        $endB = \count($b);
        while ($start < $endA && $start < $endB && $a[$start] === $b[$start]) {
            ++$start;
        }
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            --$endA;
            --$endB;
        }

        $ops = [];
        foreach (\array_slice($a, 0, $start) as $token) {
            $ops[] = ['type' => 'same', 'text' => $token];
        }
        foreach ($this->middle(\array_slice($a, $start, $endA - $start), \array_slice($b, $start, $endB - $start)) as $op) {
            $ops[] = $op;
        }
        foreach (\array_slice($a, $endA) as $token) {
            $ops[] = ['type' => 'same', 'text' => $token];
        }

        return $this->merge($ops);
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<Op>
     */
    private function middle(array $a, array $b): array
    {
        $n = \count($a);
        $m = \count($b);
        if (0 === $n || 0 === $m || $n * $m > self::MAX_CELLS) {
            return [
                ...array_map(static fn (string $t): array => ['type' => 'del', 'text' => $t], $a),
                ...array_map(static fn (string $t): array => ['type' => 'ins', 'text' => $t], $b),
            ];
        }

        // Longueurs des sous-séquences communes, remplies de la fin vers le début.
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; --$i) {
            for ($j = $m - 1; $j >= 0; --$j) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['type' => 'same', 'text' => $a[$i]];
                ++$i;
                ++$j;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['type' => 'del', 'text' => $a[$i++]];
            } else {
                $ops[] = ['type' => 'ins', 'text' => $b[$j++]];
            }
        }
        for (; $i < $n; ++$i) {
            $ops[] = ['type' => 'del', 'text' => $a[$i]];
        }
        for (; $j < $m; ++$j) {
            $ops[] = ['type' => 'ins', 'text' => $b[$j]];
        }

        return $ops;
    }

    /**
     * Regroupe les jetons consécutifs de même type.
     *
     * @param list<Op> $ops
     *
     * @return list<Op>
     */
    private function merge(array $ops): array
    {
        $merged = [];
        foreach ($ops as $op) {
            $last = array_key_last($merged);
            if (null !== $last && $merged[$last]['type'] === $op['type']) {
                $merged[$last]['text'] .= $op['text'];
            } else {
                $merged[] = $op;
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        return preg_split('/(\s+|<[^>]*>)/u', $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
