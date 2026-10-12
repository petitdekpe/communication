<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\ContentLocator;
use App\Lock\EditLockManager;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signal d'activité (prolonge le verrou d'édition) et libération du verrou, appelés par le contrôleur Stimulus edit-lock.
 */
final class LockController extends AbstractController
{
    public function __construct(
        private readonly ContentLocator $contents,
        private readonly EditLockManager $locks,
    ) {
    }

    #[Route('/admin/verrou/{resource}/{id}', name: 'app_admin_lock_touch', requirements: ['resource' => '[a-z-]+', 'id' => '\d+'], methods: ['POST'])]
    public function touch(string $resource, int $id, Request $request): JsonResponse
    {
        $content = $this->guard($resource, $id, $request);
        $holder = $this->locks->acquire($content, $this->getUser() ?? throw $this->createAccessDeniedException());

        return new JsonResponse(['holder' => $holder['name'] ?? null]);
    }

    #[Route('/admin/verrou/{resource}/{id}/liberer', name: 'app_admin_lock_release', requirements: ['resource' => '[a-z-]+', 'id' => '\d+'], methods: ['POST'])]
    public function release(string $resource, int $id, Request $request): Response
    {
        $content = $this->guard($resource, $id, $request);
        $this->locks->release($content, $this->getUser() ?? throw $this->createAccessDeniedException());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function guard(string $resource, int $id, Request $request): \App\Entity\Contract\PublishableInterface
    {
        if (!$this->contents->isValidToken('lock', $resource, $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $content = $this->contents->find($resource, $id);
        if (!$this->isGranted(Permission::CONTENT_EDIT, $content) && !$this->isGranted(Permission::CONTENT_TRANSLATE, $content)) {
            throw $this->createAccessDeniedException();
        }

        return $content;
    }
}
