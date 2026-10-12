<?php

declare(strict_types=1);

namespace App\Retention;

/**
 * Nombre de lignes purgées (ou à purger, en simulation) par catégorie.
 */
final readonly class PurgeReport
{
    public function __construct(
        public int $requests,
        public int $contributions,
        public int $subscribers,
    ) {
    }

    public function total(): int
    {
        return $this->requests + $this->contributions + $this->subscribers;
    }
}
