<?php

declare(strict_types=1);

namespace App\Document;

use App\Entity\OfficialDocument;
use App\Enum\DocumentType;
use App\Repository\OfficialDocumentRepository;
use Psr\Clock\ClockInterface;

/**
 * Construit une page de la bibliothèque à partir des paramètres d'URL
 * (type, q, from, to, sort, page). Les valeurs invalides sont ignorées : une URL partagée ne produit
 * jamais d'erreur.
 */
final class DocumentListingService
{
    public const int PER_PAGE = 10;

    public function __construct(
        private readonly OfficialDocumentRepository $documents,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $query paramètres d'URL
     */
    public function listing(array $query, string $locale): DocumentListing
    {
        $type = DocumentType::tryFrom($this->string($query, 'type'));
        $keywords = $this->string($query, 'q');
        $from = $this->date($query, 'from');
        $to = $this->date($query, 'to');
        if (null !== $from && null !== $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $newestFirst = 'date_asc' !== $this->string($query, 'sort');
        $page = max(1, (int) $this->string($query, 'page'));

        $filters = new DocumentFilters(
            locale: $locale,
            type: $type,
            keywords: '' === $keywords ? null : $keywords,
            match: '' === $keywords ? null : FulltextQuery::booleanMode($keywords),
            from: $from,
            to: $to,
            newestFirst: $newestFirst,
        );

        $paginator = $this->documents->paginate($filters, $page, self::PER_PAGE, $this->clock->now());
        $total = \count($paginator);

        $params = array_filter([
            'type' => $type?->value,
            'q' => '' === $keywords ? null : $keywords,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'sort' => $newestFirst ? null : 'date_asc',
        ], static fn (mixed $value): bool => null !== $value);

        /** @var list<OfficialDocument> $documents */
        $documents = iterator_to_array($paginator, false);

        return new DocumentListing(
            documents: $documents,
            page: $page,
            totalPages: (int) ceil($total / self::PER_PAGE),
            total: $total,
            type: $type,
            keywords: $keywords,
            from: $from?->format('Y-m-d'),
            to: $to?->format('Y-m-d'),
            newestFirst: $newestFirst,
            keywordsIgnored: $filters->matchesNothing(),
            params: $params,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function string(array $query, string $key): string
    {
        $value = $query[$key] ?? '';

        return \is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function date(array $query, string $key): ?\DateTimeImmutable
    {
        $value = $this->string($query, $key);
        $date = 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        // createFromFormat accepte « 2026-02-31 » (débordement) : on exige que la date relue soit identique.
        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
