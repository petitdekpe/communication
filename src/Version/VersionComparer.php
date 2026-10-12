<?php

declare(strict_types=1);

namespace App\Version;

use App\Entity\ContentVersion;

/**
 * Compare deux versions champ par champ. Chaque ligne porte le chemin du champ (« translations.fr.title »),
 * un indicateur de modification et la différence mot à mot.
 *
 * @phpstan-import-type Op from TextDiffer
 *
 * @phpstan-type Row array{path: string, changed: bool, ops: list<Op>}
 */
final class VersionComparer
{
    public function __construct(private readonly TextDiffer $differ)
    {
    }

    /**
     * @return list<Row>
     */
    public function compare(ContentVersion $old, ContentVersion $new): array
    {
        $a = $this->flatten($old->getSnapshot());
        $b = $this->flatten($new->getSnapshot());
        $paths = array_values(array_unique([...array_keys($a), ...array_keys($b)]));
        sort($paths);

        $rows = [];
        foreach ($paths as $path) {
            $left = $a[$path] ?? '';
            $right = $b[$path] ?? '';
            $rows[] = ['path' => $path, 'changed' => $left !== $right, 'ops' => $this->differ->diff($left, $right)];
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
            if (\is_array($value) && !array_is_list($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = match (true) {
                    \is_array($value) => implode(', ', array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '', $value)),
                    \is_bool($value) => $value ? 'oui' : 'non',
                    \is_scalar($value) => (string) $value,
                    default => '',
                };
            }
        }

        return $flat;
    }
}
