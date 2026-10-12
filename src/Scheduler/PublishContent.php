<?php

declare(strict_types=1);

namespace App\Scheduler;

/**
 * Publie un contenu dont la date programmée est échue. Routé sur le transport asynchrone (Redis).
 */
final readonly class PublishContent
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public string $entityClass,
        public int $id,
    ) {
    }
}
