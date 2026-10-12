<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminResources;
use App\Entity\ContactMessage;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Messages reçus par le formulaire « Nous contacter » : fiche de lecture.
 */
final class ContactMessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminResources $resources,
    ) {
    }

    #[Route('/admin/messages-contact/{id}', name: 'app_admin_contact_message_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $resource = $this->resources->get('contact_messages') ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(Permission::RESOURCE_VIEW, $resource);

        $message = $this->entityManager->find(ContactMessage::class, $id) ?? throw $this->createNotFoundException();

        return $this->render('admin/contact/show.html.twig', ['message' => $message]);
    }
}
