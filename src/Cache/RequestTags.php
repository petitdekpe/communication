<?php

declare(strict_types=1);

namespace App\Cache;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Étiquettes collectées pendant la construction d'une réponse (blocs dynamiques, blocs globaux, page affichée) :
 * elles étiquettent la page en cache et l'en-tête Cache-Tag.
 */
final class RequestTags implements ResetInterface
{
    /** @var array<string, true> */
    private array $tags = [];

    public function add(string ...$tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->tags);
    }

    public function reset(): void
    {
        $this->tags = [];
    }
}
