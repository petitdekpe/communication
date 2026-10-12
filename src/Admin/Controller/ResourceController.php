<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminResource;
use App\Admin\AdminResources;
use App\Admin\AdminRowFactory;
use App\Admin\ResourceListService;
use App\Entity\Contract\PublishableInterface;
use App\Enum\PublicationStatus;
use App\Security\Permission;
use App\Workflow\PublicationWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

/**
 * Listes de l'administration (une ressource = une liste filtrable, triable, paginée) et actions du circuit de
 * publication, à l'unité ou groupées. Les droits sont ceux des Voters ; les erreurs de droit répondent 403.
 */
final class ResourceController extends AbstractController
{
    public function __construct(
        private readonly AdminResources $resources,
        private readonly AdminRowFactory $rows,
        private readonly ResourceListService $lists,
        private readonly PublicationWorkflow $transitions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/liste/{resource}', name: 'app_admin_resource_list', requirements: ['resource' => '[a-z-]+'], methods: ['GET'])]
    public function list(string $resource, Request $request): Response
    {
        $definition = $this->resource($resource);

        try {
            $result = $this->lists->paginate($definition, $request->query);
        } catch (OutOfRangeCurrentPageException) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $rows = [];
        foreach ($result['pager']->getCurrentPageResults() as $entity) {
            $rows[] = $this->rows->row($definition, $entity, $locale);
        }

        return $this->render('admin/resource/list.html.twig', [
            'resource' => $definition,
            'pager' => $result['pager'],
            'rows' => $rows,
            'sort' => $result['sort'],
            'dir' => $result['dir'],
            'bulk' => $this->rows->bulkActions($definition),
            'bulkToken' => $this->rows->token($definition, 'bulk'),
            'filters' => $this->filters($definition),
            'canCreate' => $this->canCreate($definition),
        ]);
    }

    #[Route('/admin/liste/{resource}/groupe', name: 'app_admin_resource_bulk', requirements: ['resource' => '[a-z-]+'], methods: ['POST'])]
    public function bulk(string $resource, Request $request): Response
    {
        $definition = $this->resource($resource);
        if (!$definition->workflow || !$this->rows->isValidToken($definition, 'bulk', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $transition = $request->request->getString('transition');
        if (!\in_array($transition, ['submit', 'publish', 'unpublish'], true)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_filter(array_map('intval', $request->request->all('ids')), static fn (int $id): bool => $id > 0);
        $done = 0;
        $skipped = 0;
        foreach ($this->entityManager->getRepository($definition->entity)->findBy(['id' => $ids]) as $entity) {
            if ($entity instanceof PublishableInterface && $this->transitions->can($transition, $entity)) {
                $this->transitions->apply($transition, $entity);
                ++$done;
            } else {
                ++$skipped;
            }
        }

        $this->addFlash($done > 0 ? 'success' : 'warning', $done > 0 ? 'admin.flash.bulk_done' : 'admin.flash.bulk_none');
        if ($skipped > 0 && $done > 0) {
            $this->addFlash('warning', 'admin.flash.bulk_skipped');
        }

        return $this->redirectToRoute('app_admin_resource_list', ['resource' => $definition->id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/liste/{resource}/{id}/{transition}', name: 'app_admin_resource_action', requirements: ['resource' => '[a-z-]+', 'id' => '\d+', 'transition' => '[a-z]+'], methods: ['POST'])]
    public function action(string $resource, int $id, string $transition, Request $request): Response
    {
        $definition = $this->resource($resource);
        if (!$definition->workflow || !$this->rows->isValidToken($definition, (string) $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entity = $this->entityManager->find($definition->entity, $id) ?? throw $this->createNotFoundException();
        if (!$entity instanceof PublishableInterface) {
            throw $this->createNotFoundException();
        }

        if ('delete' === $transition) {
            $this->denyAccessUnlessGranted(Permission::CONTENT_DELETE, $entity);
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $message = 'admin.flash.deleted';
            $removed = true;
        } else {
            if (!\in_array($transition, PublicationWorkflow::names(), true) || !$this->transitions->can($transition, $entity)) {
                throw $this->createAccessDeniedException();
            }
            // Les transitions à motif (refus) ne se font que depuis le formulaire du contenu.
            if (PublicationWorkflow::requiresReason($transition)) {
                throw new BadRequestHttpException('Un motif est obligatoire : utilisez le formulaire du contenu.');
            }
            $this->transitions->apply($transition, $entity);
            $message = 'admin.flash.'.$transition;
            $removed = false;
        }

        // Turbo Stream : la ligne est remplacée (ou retirée) et un toast ajouté, sans recharger la page.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('admin/resource/_action.stream.html.twig', [
                'resource' => $definition,
                'row' => $removed ? null : $this->rows->row($definition, $entity, $request->getLocale()),
                'rowId' => $id,
                'columns' => $definition->columns,
                'bulk' => $this->rows->bulkActions($definition),
                'message' => $message,
            ]);
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_admin_resource_list', ['resource' => $definition->id], Response::HTTP_SEE_OTHER);
    }

    private function canCreate(AdminResource $resource): bool
    {
        if (null === $resource->newRoute) {
            return false;
        }

        return $resource->workflow ? $this->isGranted(Permission::CONTENT_CREATE, $resource->entity) : $this->isGranted(Permission::USER_MANAGE);
    }

    private function resource(string $id): AdminResource
    {
        $resource = $this->resources->get($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(Permission::RESOURCE_VIEW, $resource);

        return $resource;
    }

    /**
     * @return list<array{name: string, label: string, type: string, options?: array<string, string>}>
     */
    private function filters(AdminResource $resource): array
    {
        $filters = [['name' => 'q', 'label' => 'admin.filter.search', 'type' => 'text']];
        if ($resource->workflow) {
            $options = [];
            foreach (PublicationStatus::cases() as $status) {
                $options[$status->value] = 'admin.status.publication.'.$status->value;
            }
            $filters[] = ['name' => 'status', 'label' => 'admin.column.status', 'type' => 'select', 'options' => $options];
        }
        if ('requests' === $resource->id) {
            $options = [];
            foreach (\App\Enum\RequestStatus::cases() as $status) {
                $options[$status->value] = 'admin.status.request.'.$status->value;
            }
            $filters[] = ['name' => 'status', 'label' => 'admin.column.status', 'type' => 'select', 'options' => $options];
        }

        return $filters;
    }
}
