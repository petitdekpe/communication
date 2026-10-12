<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\AuditLog;
use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\ServiceRequest;
use App\Enum\PublicationStatus;
use App\Enum\RequestStatus;
use App\Repository\AuditLogRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Données du tableau de bord, limitées à ce que le rôle de l'utilisateur lui permet de voir :
 * contenus à relire (relecteurs), demandes en attente (presse), traductions anglaises manquantes (éditeurs et
 * traducteurs), dernières publications (journal d'audit).
 *
 * @phpstan-type Item array{resource: string, label: string, title: string, url: string, date: \DateTimeImmutable|null}
 */
final class DashboardData
{
    private const int LIMIT = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminResources $resources,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /**
     * Contenus soumis, en attente d'une relecture.
     *
     * @return array{count: int, items: list<Item>}
     */
    public function toReview(string $locale): array
    {
        return $this->contents(PublicationStatus::InReview, Permission::CONTENT_PUBLISH, $locale);
    }

    /**
     * Demandes reçues ou en cours d'examen.
     *
     * @return array{count: int, items: list<array{number: string, service: string, url: string, status: string, date: \DateTimeImmutable|null}>}
     */
    public function pendingRequests(string $locale): array
    {
        if (!$this->security->isGranted(Permission::REQUEST_VIEW)) {
            return ['count' => 0, 'items' => []];
        }

        $qb = $this->entityManager->createQueryBuilder()->select('r')->from(ServiceRequest::class, 'r')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', [RequestStatus::Received, RequestStatus::InReview])
            ->orderBy('r.createdAt', 'ASC');
        $count = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $items = [];
        /** @var list<ServiceRequest> $requests */
        $requests = $qb->setMaxResults(self::LIMIT)->getQuery()->getResult();
        foreach ($requests as $request) {
            $items[] = [
                'number' => $request->getTrackingNumber(),
                'service' => (string) $request->getService()->translate($locale)->getTitle(),
                'url' => $this->urlGenerator->generate('app_admin_request_show', ['id' => $request->getId()]),
                'status' => $request->getStatus()->value,
                'date' => $request->getCreatedAt(),
            ];
        }

        return ['count' => $count, 'items' => $items];
    }

    /**
     * Contenus traduisibles qui n'ont pas de version anglaise.
     *
     * @return array{count: int, items: list<Item>}
     */
    public function missingTranslations(string $locale): array
    {
        $count = 0;
        $items = [];
        foreach ($this->resources->all() as $resource) {
            $translationClass = $resource->translationClass();
            if (null === $translationClass || !$resource->workflow || !$this->security->isGranted(Permission::CONTENT_TRANSLATE, $resource->entity)) {
                continue;
            }

            $qb = $this->entityManager->createQueryBuilder()->select('e')->from($resource->entity, 'e')
                ->andWhere('e.status != :archived')
                ->andWhere(\sprintf("e.id NOT IN (SELECT IDENTITY(t.translatable) FROM %s t WHERE t.locale = 'en')", $translationClass))
                ->setParameter('archived', PublicationStatus::Archived);
            $count += (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

            /** @var list<object> $entities */
            $entities = $qb->orderBy('e.updatedAt', 'DESC')->setMaxResults(self::LIMIT)->getQuery()->getResult();
            foreach ($entities as $entity) {
                if (\count($items) < self::LIMIT && $entity instanceof PublishableInterface && $entity instanceof TranslatableInterface) {
                    $items[] = $this->item($resource, $entity, $locale);
                }
            }
        }

        return ['count' => $count, 'items' => $items];
    }

    /**
     * @return list<AuditLog>
     */
    public function latestPublications(): array
    {
        return $this->audit->latestPublications(5);
    }

    /**
     * @return array{count: int, items: list<Item>}
     */
    private function contents(PublicationStatus $status, string $permission, string $locale): array
    {
        $count = 0;
        $items = [];
        foreach ($this->resources->all() as $resource) {
            if (!$resource->workflow || !$this->security->isGranted($permission, $resource->entity)) {
                continue;
            }

            $qb = $this->entityManager->createQueryBuilder()->select('e')->from($resource->entity, 'e')
                ->andWhere('e.status = :status')->setParameter('status', $status);
            $count += (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

            /** @var list<object> $entities */
            $entities = $qb->orderBy('e.updatedAt', 'ASC')->setMaxResults(self::LIMIT)->getQuery()->getResult();
            foreach ($entities as $entity) {
                if (\count($items) < self::LIMIT && $entity instanceof PublishableInterface && $entity instanceof TranslatableInterface) {
                    $items[] = $this->item($resource, $entity, $locale);
                }
            }
        }

        return ['count' => $count, 'items' => $items];
    }

    /**
     * @return Item
     */
    private function item(AdminResource $resource, PublishableInterface&TranslatableInterface $entity, string $locale): array
    {
        $translation = $entity->translate($locale);
        $title = method_exists($translation, 'getTitle') ? $translation->getTitle() : (method_exists($translation, 'getLabel') ? $translation->getLabel() : null);
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        $url = null !== $resource->editRoute && null !== $id
            ? $this->urlGenerator->generate($resource->editRoute, ['id' => $id])
            : $this->urlGenerator->generate('app_admin_resource_list', ['resource' => $resource->id]);

        return ['resource' => $resource->id, 'label' => $resource->label, 'title' => (string) $title, 'url' => $url, 'date' => $entity->getUpdatedAt()];
    }
}
