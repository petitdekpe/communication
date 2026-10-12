<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\MenuItemType;
use App\Entity\MenuItem;
use App\Menu\MenuEditor;
use App\Menu\MenuItemData;
use App\Menu\MenuProvider;
use App\Repository\MenuItemRepository;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Éditeur des menus du site (en-tête, pied de page, accès directs) : arbre réordonnable par glisser-déposer ou au
 * clavier, libellés FR/EN, lien vers un contenu du site ou une adresse externe. Chaque enregistrement vide le cache
 * du menu : la modification est visible sur le site aussitôt.
 */
final class MenuController extends AbstractController
{
    private const string MENUS = 'main|utility|footer';

    public function __construct(
        private readonly MenuEditor $editor,
        private readonly MenuItemRepository $items,
    ) {
    }

    #[Route('/admin/menus', name: 'app_admin_menus', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);

        return $this->redirectToRoute('app_admin_menu', ['menu' => MenuProvider::MAIN_MENU]);
    }

    #[Route('/admin/menus/{menu}', name: 'app_admin_menu', requirements: ['menu' => self::MENUS], methods: ['GET'])]
    public function show(string $menu, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);

        return $this->render('admin/menu/editor.html.twig', [
            'menu' => $menu,
            'menus' => array_keys(MenuProvider::EDITABLE_MENUS),
            'maxDepth' => MenuProvider::EDITABLE_MENUS[$menu],
            'tree' => $this->editor->tree($menu, $request->getLocale()),
            'empty' => $this->editor->isEmpty($menu),
        ]);
    }

    /** Enregistre l'ordre et l'imbrication envoyés par l'arbre (JSON : {"tree": [{"id": 1, "children": [...]}]}). */
    #[Route('/admin/menus/{menu}/ordre', name: 'app_admin_menu_reorder', requirements: ['menu' => self::MENUS], methods: ['POST'])]
    public function reorder(string $menu, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);
        if (!$this->isCsrfTokenValid('admin_menu_order', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException();
        }

        $payload = json_decode($request->getContent(), true);
        $tree = \is_array($payload) && \is_array($payload['tree'] ?? null) ? array_values($payload['tree']) : null;
        if (null === $tree || !$this->editor->reorder($menu, $tree)) {
            return new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/menus/{menu}/importer', name: 'app_admin_menu_import', requirements: ['menu' => self::MENUS], methods: ['POST'])]
    public function import(string $menu, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);
        if (!$this->isCsrfTokenValid('admin_menu_import_'.$menu, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->editor->importDefaults($menu);
        $this->addFlash('success', 'admin.menus.flash.imported');

        return $this->redirectToRoute('app_admin_menu', ['menu' => $menu], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/menus/{menu}/entrees/nouvelle', name: 'app_admin_menu_item_new', requirements: ['menu' => self::MENUS], methods: ['GET', 'POST'])]
    public function new(string $menu, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);

        $form = $this->createForm(MenuItemType::class, new MenuItemData(), ['parents' => $this->parents($menu, null)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->editor->create($menu, $form->getData());
            $this->addFlash('success', 'admin.menus.flash.created');

            return $this->redirectToRoute('app_admin_menu', ['menu' => $menu], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $menu, null, $request);
    }

    #[Route('/admin/menus/entrees/{id}', name: 'app_admin_menu_item_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);
        $item = $this->find($id);
        $menu = (string) $item->getMenu();

        $form = $this->createForm(MenuItemType::class, $this->editor->dataFor($item), ['parents' => $this->parents($menu, $item)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->editor->update($item, $form->getData());
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_menu', ['menu' => $menu], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $menu, $item, $request);
    }

    #[Route('/admin/menus/entrees/{id}/supprimer', name: 'app_admin_menu_item_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);
        $item = $this->find($id);
        if (!$this->isCsrfTokenValid('admin_menu_delete_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $menu = (string) $item->getMenu();
        $this->editor->delete($item);
        $this->addFlash('success', 'admin.menus.flash.deleted');

        return $this->redirectToRoute('app_admin_menu', ['menu' => $menu], Response::HTTP_SEE_OTHER);
    }

    /** Recherche d'un contenu à lier (JSON), utilisée par le champ de recherche du formulaire. */
    #[Route('/admin/menus/contenus', name: 'app_admin_menu_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MENU_MANAGE);

        return $this->json($this->editor->search($request->query->getString('q'), $request->getLocale()));
    }

    private function find(int $id): MenuItem
    {
        $item = $this->items->find($id);
        if (!$item instanceof MenuItem || !isset(MenuProvider::EDITABLE_MENUS[(string) $item->getMenu()])) {
            throw $this->createNotFoundException();
        }

        return $item;
    }

    /**
     * Groupes pouvant accueillir l'entrée : entrées de premier niveau d'un menu à deux niveaux, sauf l'entrée elle-même
     * et toute entrée qui a déjà des enfants (elle ne peut pas devenir enfant à son tour).
     *
     * @return list<MenuItem>
     */
    private function parents(string $menu, ?MenuItem $current): array
    {
        if (MenuProvider::EDITABLE_MENUS[$menu] < 2 || (null !== $current && $this->hasChildren($menu, $current))) {
            return [];
        }

        return array_values(array_filter(
            $this->items->findForMenu($menu),
            static fn (MenuItem $candidate): bool => null === $candidate->getParent() && $candidate->getId() !== $current?->getId(),
        ));
    }

    private function hasChildren(string $menu, MenuItem $item): bool
    {
        foreach ($this->items->findForMenu($menu) as $candidate) {
            if ($candidate->getParent()?->getId() === $item->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param FormInterface<MenuItemData> $form
     */
    private function renderForm(FormInterface $form, string $menu, ?MenuItem $item, Request $request): Response
    {
        /** @var MenuItemData $data */
        $data = $form->getData();

        return $this->render('admin/menu/form.html.twig', [
            'form' => $form,
            'menu' => $menu,
            'item' => $item,
            'targetLabel' => $this->editor->targetLabel($data->target, $request->getLocale()),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
