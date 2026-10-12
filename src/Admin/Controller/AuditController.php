<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Audit\AuditFilters;
use App\Repository\AuditLogRepository;
use App\Security\Permission;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal d'audit : qui a fait quoi et quand, avec les valeurs avant/après. Consultable et filtrable ; en lecture seule.
 */
final class AuditController extends AbstractController
{
    private const int PER_PAGE = 25;

    #[Route('/admin/audit', name: 'app_admin_audit', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $logs): Response
    {
        $this->denyAccessUnlessGranted(Permission::AUDIT_VIEW);

        $filters = AuditFilters::fromQuery($request->query);
        $pager = new Pagerfanta(new QueryAdapter($logs->queryBuilder($filters), false, false));
        $pager->setMaxPerPage(self::PER_PAGE);
        $pager->setCurrentPage(min(max(1, $request->query->getInt('page', 1)), max(1, $pager->getNbPages())));

        $actions = [];
        foreach (['create', 'update', 'submit', 'publish', 'unpublish', 'archive', 'delete'] as $action) {
            $actions[$action] = 'admin.audit.action.'.$action;
        }
        $entities = [];
        foreach ($logs->entityClasses() as $class) {
            $entities[$class] = $class;
        }

        return $this->render('admin/audit/index.html.twig', [
            'pager' => $pager,
            'fields' => [
                ['name' => 'actor', 'label' => 'admin.audit.actor', 'type' => 'text'],
                ['name' => 'action', 'label' => 'admin.audit.action_label', 'type' => 'select', 'options' => $actions],
                ['name' => 'entity', 'label' => 'admin.audit.entity', 'type' => 'select', 'options' => $entities],
                ['name' => 'from', 'label' => 'admin.audit.from', 'type' => 'date'],
                ['name' => 'to', 'label' => 'admin.audit.to', 'type' => 'date'],
            ],
            'query' => array_filter($request->query->all(), static fn (mixed $v, string $k): bool => 'page' !== $k && \is_string($v), \ARRAY_FILTER_USE_BOTH),
        ]);
    }
}
