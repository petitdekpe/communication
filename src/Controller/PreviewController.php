<?php

declare(strict_types=1);

namespace App\Controller;

use App\Admin\AdminResources;
use App\Entity\Contract\PublishableInterface;
use App\Preview\PreviewRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Aperçu d'un contenu non publié avec le rendu public. Accessible uniquement par un lien signé et non expiré
 * (voir PreviewLinks) ; la page n'est ni mise en cache ni indexée (_sensitive).
 */
final class PreviewController extends AbstractController
{
    #[Route(
        path: ['fr' => '/apercu/{resource}/{id}', 'en' => '/preview/{resource}/{id}'],
        name: 'app_preview',
        requirements: ['resource' => '[a-z-]+', 'id' => '\d+'],
        defaults: ['_sensitive' => true],
        methods: ['GET'],
    )]
    public function show(string $resource, int $id, Request $request, UriSigner $uriSigner, AdminResources $resources, EntityManagerInterface $entityManager, PreviewRenderer $renderer): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('Lien d\'aperçu invalide ou expiré.');
        }

        $definition = $resources->get($resource);
        if (null === $definition || !\in_array($definition->id, PreviewRenderer::RESOURCES, true)) {
            throw $this->createNotFoundException();
        }
        $content = $entityManager->find($definition->entity, $id);
        if (!$content instanceof PublishableInterface) {
            throw $this->createNotFoundException();
        }

        $rendered = $renderer->render($content, $request->getLocale()) ?? throw $this->createNotFoundException();

        return $this->render($rendered['template'], $rendered['context']);
    }
}
