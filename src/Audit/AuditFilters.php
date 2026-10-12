<?php

declare(strict_types=1);

namespace App\Audit;

use Symfony\Component\HttpFoundation\InputBag;

/**
 * Filtres du journal d'audit, lus dans l'URL (?actor=…&action=…&entity=…&from=…&to=…).
 */
final class AuditFilters
{
    public function __construct(
        public readonly ?string $actor = null,
        public readonly ?string $action = null,
        public readonly ?string $entity = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
    ) {
    }

    /**
     * @param InputBag<string> $query
     */
    public static function fromQuery(InputBag $query): self
    {
        $text = static function (string $key) use ($query): ?string {
            $value = $query->get($key);

            return \is_string($value) && '' !== trim($value) ? mb_substr(trim($value), 0, 180) : null;
        };
        $date = static function (string $key) use ($text): ?\DateTimeImmutable {
            $value = $text($key);
            $parsed = null === $value ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return false === $parsed ? null : $parsed;
        };

        return new self($text('actor'), $text('action'), $text('entity'), $date('from'), $date('to'));
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'actor' => $this->actor,
            'action' => $this->action,
            'entity' => $this->entity,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
        ], static fn (?string $v): bool => null !== $v);
    }
}
