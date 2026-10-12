<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\ContentLocator;
use App\Workflow\PublicationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Transitions du circuit de publication appliquées depuis l'en-tête du formulaire (composant WorkflowActions) :
 * refus avec motif obligatoire, publication immédiate ou programmée, annulation de la programmation.
 * Les droits sont ceux des Voters (garde du workflow) ; un refus de droit répond 403.
 */
final class WorkflowController extends AbstractController
{
    public function __construct(
        private readonly ContentLocator $contents,
        private readonly PublicationWorkflow $workflow,
        #[Autowire('%app.timezone%')]
        private readonly string $timezone,
    ) {
    }

    #[Route('/admin/flux/{resource}/{id}/{transition}', name: 'app_admin_workflow_apply', requirements: ['resource' => '[a-z-]+', 'id' => '\d+', 'transition' => '[a-z]+'], methods: ['POST'])]
    public function apply(string $resource, int $id, string $transition, Request $request): Response
    {
        if (!$this->contents->isValidToken('workflow', $resource, $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $definition = $this->contents->resource($resource);
        $content = $this->contents->find($resource, $id);

        try {
            if ('unschedule' === $transition) {
                $this->workflow->unschedule($content);
                $message = 'workflow.flash.unscheduled';
            } elseif (!\in_array($transition, PublicationWorkflow::names(), true)) {
                throw $this->createNotFoundException();
            } elseif (!$this->workflow->can($transition, $content)) {
                throw $this->createAccessDeniedException();
            } elseif ('publish' === $transition && '' !== $scheduled = trim($request->request->getString('scheduled_at'))) {
                $this->workflow->schedule($content, $this->parseDate($scheduled));
                $message = 'workflow.flash.scheduled';
            } else {
                $this->workflow->apply($transition, $content, $request->request->getString('reason'));
                $message = 'admin.flash.'.$transition;
            }
            $this->addFlash('success', $message);
        } catch (\DomainException $exception) {
            $this->addFlash('error', PublicationWorkflow::requiresReason($transition) ? 'workflow.error.reason_required' : 'workflow.error.invalid');
            unset($exception);
        }

        return null === $definition->editRoute
            ? $this->redirectToRoute('app_admin_resource_list', ['resource' => $definition->id], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute($definition->editRoute, ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    /**
     * Saisie « datetime-local » dans le fuseau d'affichage, stockée en UTC.
     */
    private function parseDate(string $value): \DateTimeImmutable
    {
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone($this->timezone)))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new \DomainException('Date invalide.');
        }
    }
}
