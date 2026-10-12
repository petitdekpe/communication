<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminResources;
use App\Entity\ConsultationContribution;
use App\Security\Permission;
use App\ServiceRequest\AttachmentResponder;
use App\ServiceRequest\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contributions aux consultations : fiche de lecture et téléchargement du fichier joint, servi par ce contrôleur
 * seulement (stockage privé, hors racine web).
 */
final class ContributionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminResources $resources,
    ) {
    }

    #[Route('/admin/contributions/{id}', name: 'app_admin_contribution_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $contribution = $this->find($id);

        return $this->render('admin/contribution/show.html.twig', ['contribution' => $contribution]);
    }

    #[Route('/admin/contributions/{id}/fichier', name: 'app_admin_contribution_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id, AttachmentStorage $storage, AttachmentResponder $responder): Response
    {
        $contribution = $this->find($id);
        $path = $contribution->getFilePath();
        if (null === $path || !$storage->existsByPath($path)) {
            throw $this->createNotFoundException();
        }

        return $responder->download($path, (string) $contribution->getFileMimeType(), 'contribution-'.(int) $contribution->getId());
    }

    private function find(int $id): ConsultationContribution
    {
        $resource = $this->resources->get('contributions');
        if (null === $resource) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(Permission::RESOURCE_VIEW, $resource);

        return $this->entityManager->find(ConsultationContribution::class, $id) ?? throw $this->createNotFoundException();
    }
}
