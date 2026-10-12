<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\ContentLocator;
use App\Security\Permission;
use App\Version\VersionComparer;
use App\Version\VersionRecorder;
use App\Workflow\ContentLabeler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Versions d'un contenu : liste, comparaison côte à côte de deux versions (différence surlignée) et restauration.
 */
final class VersionController extends AbstractController
{
    public function __construct(
        private readonly ContentLocator $contents,
        private readonly VersionRecorder $versions,
        private readonly VersionComparer $comparer,
        private readonly ContentLabeler $labeler,
    ) {
    }

    #[Route('/admin/versions/{resource}/{id}', name: 'app_admin_versions', requirements: ['resource' => '[a-z-]+', 'id' => '\d+'], methods: ['GET'])]
    public function index(string $resource, int $id, Request $request): Response
    {
        $definition = $this->contents->resource($resource);
        $content = $this->contents->find($resource, $id);
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW, $content);

        $history = $this->versions->history($content);
        $old = $this->versions->find($content, $request->query->getInt('a'));
        $new = $this->versions->find($content, $request->query->getInt('b'));
        // Ordre chronologique : la plus ancienne à gauche.
        if (null !== $old && null !== $new && $old->getNumber() > $new->getNumber()) {
            [$old, $new] = [$new, $old];
        }

        return $this->render('admin/version/index.html.twig', [
            'resource' => $definition,
            'content' => $content,
            'title' => $this->labeler->label($content, $request->getLocale()),
            'versions' => $history,
            'old' => $old,
            'new' => $new,
            'rows' => null !== $old && null !== $new ? $this->comparer->compare($old, $new) : [],
            'canRestore' => $this->isGranted(Permission::CONTENT_EDIT, $content),
            'restoreToken' => $this->contents->token('restore', $resource, $id),
        ]);
    }

    #[Route('/admin/versions/{resource}/{id}/{number}/restaurer', name: 'app_admin_version_restore', requirements: ['resource' => '[a-z-]+', 'id' => '\d+', 'number' => '\d+'], methods: ['POST'])]
    public function restore(string $resource, int $id, int $number, Request $request): Response
    {
        if (!$this->contents->isValidToken('restore', $resource, $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $definition = $this->contents->resource($resource);
        $content = $this->contents->find($resource, $id);
        $this->denyAccessUnlessGranted(Permission::CONTENT_EDIT, $content);
        $version = $this->versions->find($content, $number) ?? throw $this->createNotFoundException();

        $this->versions->restore($content, $version);
        $this->addFlash('success', 'workflow.flash.restored');

        return null === $definition->editRoute
            ? $this->redirectToRoute('app_admin_versions', ['resource' => $resource, 'id' => $id], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute($definition->editRoute, ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
