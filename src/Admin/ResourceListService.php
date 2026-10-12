<?php

declare(strict_types=1);

namespace App\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Requête d'une liste de l'administration : recherche (?q), filtre de statut (?status), tri (?sort, ?dir) et
 * pagination (?page), tous lus dans l'URL. Les colonnes triables sont une liste blanche : une valeur inconnue est ignorée.
 */
final class ResourceListService
{
    public const int PER_PAGE = 20;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param InputBag<covariant bool|int|float|string|null> $query
     *
     * @return array{pager: Pagerfanta<object>, sort: string, dir: string}
     *
     * @throws OutOfRangeCurrentPageException
     */
    public function paginate(AdminResource $resource, InputBag $query): array
    {
        $qb = $this->entityManager->createQueryBuilder()->select('e')->from($resource->entity, 'e');

        $this->search($qb, $resource, $this->text($query, 'q'));

        $status = $this->text($query, 'status');
        if ($resource->workflow && null !== $status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }
        $this->extraFilters($qb, $resource, $query);

        [$sortKey, $dir] = $this->sort($qb, $resource, $this->text($query, 'sort'), 'desc' === $this->text($query, 'dir') ? 'DESC' : 'ASC');
        $qb->addOrderBy('e.id', 'DESC');

        $pager = new Pagerfanta(new QueryAdapter($qb, false, false));
        $pager->setMaxPerPage(self::PER_PAGE);
        $page = max(1, $query->getInt('page', 1));
        $pager->setCurrentPage(min($page, max(1, $pager->getNbPages())));

        return ['pager' => $pager, 'sort' => $sortKey, 'dir' => strtolower($dir)];
    }

    private function search(QueryBuilder $qb, AdminResource $resource, ?string $term): void
    {
        if (null === $term) {
            return;
        }

        $conditions = [];
        foreach ($resource->searchFields as $field) {
            $conditions[] = \sprintf("e.%s LIKE :q ESCAPE '!'", $field);
        }
        $translationClass = $resource->translationClass();
        if (null !== $translationClass && [] !== $resource->translationSearchFields) {
            $fields = array_map(static fn (string $f): string => \sprintf("t.%s LIKE :q ESCAPE '!'", $f), $resource->translationSearchFields);
            $conditions[] = \sprintf('e.id IN (SELECT IDENTITY(t.translatable) FROM %s t WHERE %s)', $translationClass, implode(' OR ', $fields));
        }
        if ([] === $conditions) {
            return;
        }

        $qb->andWhere(implode(' OR ', $conditions))
            ->setParameter('q', '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
    }

    /**
     * Filtres propres à certaines ressources (statut d'une demande, public d'une démarche…), lus dans l'URL.
     *
     * @param InputBag<covariant bool|int|float|string|null> $query
     */
    private function extraFilters(QueryBuilder $qb, AdminResource $resource, InputBag $query): void
    {
        foreach (['requests' => 'status', 'subscribers' => 'status', 'services' => 'audience', 'articles' => 'type', 'documents' => 'type', 'events' => 'type'] as $id => $field) {
            $value = $this->text($query, $field);
            if ($resource->id === $id && null !== $value && (!$resource->workflow || 'status' !== $field)) {
                $qb->andWhere(\sprintf('e.%s = :extra', $field))->setParameter('extra', $value);
            }
        }
    }

    /**
     * @return array{0: string, 1: string} clé de colonne triée et sens
     */
    private function sort(QueryBuilder $qb, AdminResource $resource, ?string $requested, string $dir): array
    {
        foreach ($resource->columns as $column) {
            if (($column['sort'] ?? null) && $column['key'] === $requested) {
                $qb->orderBy($column['sort'], $dir);

                return [$column['key'], $dir];
            }
        }

        $default = $resource->defaultSort;
        $qb->orderBy($default, \in_array($default, ['e.position', 'e.name', 'e.email'], true) ? 'ASC' : 'DESC');

        return ['', 'DESC'];
    }

    /**
     * @param InputBag<covariant bool|int|float|string|null> $query
     */
    private function text(InputBag $query, string $key): ?string
    {
        $value = $query->get($key);

        return \is_string($value) && '' !== trim($value) ? mb_substr(trim($value), 0, 100) : null;
    }
}
