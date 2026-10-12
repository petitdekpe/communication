<?php

declare(strict_types=1);

namespace App\Translation;

use App\Admin\AdminResource;
use App\Admin\AdminResources;
use App\Entity\Contract\TranslatableInterface;
use App\Enum\PublicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Contenus publiés qui n'ont pas de version anglaise, par ressource (articles, documents, démarches…).
 *
 * @phpstan-type Missing array{resource: AdminResource, total: int, items: list<array{id: int, title: string, entity: TranslatableInterface}>}
 */
final class TranslationDashboard
{
    /** Contenus listés (et exportés) par ressource. */
    public const int LIMIT = 200;

    public function __construct(
        private readonly AdminResources $resources,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Missing>
     */
    public function missing(?string $onlyResource = null): array
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $groups = [];
        foreach ($this->resources->all() as $resource) {
            $translationClass = $resource->translationClass();
            if (null === $translationClass || !$resource->workflow || (null !== $onlyResource && $resource->id !== $onlyResource)) {
                continue;
            }

            $qb = $this->entityManager->createQueryBuilder()->select('e')->from($resource->entity, 'e')
                ->andWhere('e.status = :published')
                ->andWhere(\sprintf("e.id NOT IN (SELECT IDENTITY(t.translatable) FROM %s t WHERE t.locale = 'en')", $translationClass))
                ->setParameter('published', PublicationStatus::Published);
            $total = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
            if (0 === $total) {
                continue;
            }

            /** @var list<object> $entities */
            $entities = $qb->orderBy('e.updatedAt', 'DESC')->setMaxResults(self::LIMIT)->getQuery()->getResult();
            $titlePath = $this->titlePath($resource);
            $items = [];
            foreach ($entities as $entity) {
                if ($entity instanceof TranslatableInterface && method_exists($entity, 'getId')) {
                    $title = $accessor->getValue($entity->translate('fr'), $titlePath);
                    $items[] = ['id' => (int) $entity->getId(), 'title' => \is_string($title) ? $title : '', 'entity' => $entity];
                }
            }
            $groups[] = ['resource' => $resource, 'total' => $total, 'items' => $items];
        }

        return $groups;
    }

    /** Chemin de la propriété « titre » de la traduction (title, label, name…), d'après la colonne titre de la liste. */
    public function titlePath(AdminResource $resource): string
    {
        foreach ($resource->columns as $column) {
            if ('title' === $column['type']) {
                return $column['path'];
            }
        }

        return 'title';
    }
}
