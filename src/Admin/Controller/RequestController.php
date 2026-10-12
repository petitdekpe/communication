<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestAttachment;
use App\Enum\RequestStatus;
use App\Security\Permission;
use App\ServiceRequest\AttachmentResponder;
use App\ServiceRequest\AttachmentStorage;
use App\ServiceRequest\RequestStatusUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demandes des usagers (accréditations presse, etc.) : consultation, changement de statut avec commentaire interne
 * (le demandeur est prévenu par e-mail), téléchargement des pièces.
 * Réservé au rôle presse (et à l'administrateur par héritage). Les pièces ne sont jamais servies autrement que par ici.
 */
final class RequestController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/admin/demandes/{id}', name: 'app_admin_request_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $serviceRequest = $this->find($id);
        $this->denyAccessUnlessGranted(Permission::REQUEST_VIEW, $serviceRequest);

        return $this->render('admin/request/show.html.twig', [
            'request' => $serviceRequest,
            'statuses' => RequestStatus::cases(),
            'canProcess' => $this->isGranted(Permission::REQUEST_PROCESS, $serviceRequest),
        ]);
    }

    #[Route('/admin/demandes/{id}/statut', name: 'app_admin_request_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(int $id, Request $request, RequestStatusUpdater $updater): Response
    {
        $serviceRequest = $this->find($id);
        $this->denyAccessUnlessGranted(Permission::REQUEST_PROCESS, $serviceRequest);
        if (!$this->isCsrfTokenValid('admin_request_status_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = RequestStatus::tryFrom($request->request->getString('status'));
        if (null === $status) {
            throw $this->createAccessDeniedException();
        }

        // Sans champ « note » dans la requête, le commentaire interne reste tel quel ; un champ vide l'efface.
        $note = $request->request->has('note') ? mb_substr($request->request->getString('note'), 0, 5000) : null;
        $changed = $updater->update($serviceRequest, $status, $note);
        $this->addFlash('success', $changed ? 'admin.flash.status_updated' : 'admin.flash.saved');

        return $this->redirectToRoute('app_admin_request_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/demandes/{id}/pieces/{attachment}', name: 'app_admin_request_attachment', requirements: ['id' => '\d+', 'attachment' => '\d+'], methods: ['GET'])]
    public function attachment(int $id, int $attachment, AttachmentStorage $storage, AttachmentResponder $responder): Response
    {
        $serviceRequest = $this->find($id);
        $this->denyAccessUnlessGranted(Permission::REQUEST_VIEW, $serviceRequest);

        $file = $this->entityManager->find(ServiceRequestAttachment::class, $attachment);
        if (!$file instanceof ServiceRequestAttachment || $file->getRequest() !== $serviceRequest || !$storage->exists($file)) {
            throw $this->createNotFoundException();
        }

        return $responder->download($file->getPath(), $file->getMimeType(), 'piece-'.(int) $file->getId());
    }

    private function find(int $id): ServiceRequest
    {
        return $this->entityManager->find(ServiceRequest::class, $id) ?? throw $this->createNotFoundException();
    }
}
