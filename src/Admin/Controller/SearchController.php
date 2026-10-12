<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminResources;
use App\Admin\AdminRowFactory;
use App\Admin\ResourceListService;
use App\Search\SearchService;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche de l'administration : parcourt toutes les listes que l'utilisateur peut voir, tous statuts confondus
 * (brouillons compris, contrairement à la recherche publique), et renvoie les premiers résultats de chacune.
 */
final class SearchController extends AbstractController
{
    private const int PER_RESOURCE = 5;

    #[Route('/admin/recherche', name: 'app_admin_search', methods: ['GET'])]
    public function index(Request $request, AdminResources $resources, ResourceListService $lists, AdminRowFactory $rows): Response
    {
        $query = SearchService::normalize($request->query->getString('q'));

        $groups = [];
        if (mb_strlen($query) >= SearchService::MIN_LENGTH) {
            foreach ($resources->all() as $resource) {
                if (!$this->isGranted(Permission::RESOURCE_VIEW, $resource) || ([] === $resource->searchFields && [] === $resource->translationSearchFields)) {
                    continue;
                }
                $result = $lists->paginate($resource, new InputBag(['q' => $query]));
                $pager = $result['pager'];
                $pager->setMaxPerPage(self::PER_RESOURCE);
                $items = [];
                foreach ($pager->getCurrentPageResults() as $entity) {
                    $row = $rows->row($resource, $entity, $request->getLocale());
                    $first = array_key_first($row['cells']);
                    $items[] = ['title' => $row['title'], 'url' => $row['cells'][$first]['href'] ?? null, 'status' => $row['cells']['status']['value'] ?? null];
                }
                if ([] !== $items) {
                    $groups[] = ['resource' => $resource, 'total' => $pager->getNbResults(), 'items' => $items];
                }
            }
        }

        return $this->render('admin/search.html.twig', ['query' => $query, 'groups' => $groups]);
    }
}
