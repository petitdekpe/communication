<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\DocumentAdminType;
use App\Admin\Form\DocumentData;
use App\Admin\TranslationApplier;
use App\Entity\OfficialDocument;
use App\Entity\OfficialDocumentTranslation;
use App\Media\MediaStorage;
use App\Security\Permission;
use App\Version\VersionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création et modification d'un document officiel (fichier, référence, date, titre et résumé en deux langues).
 */
final class DocumentController extends AbstractController
{
    private const array FIELD_NAMES = ['title', 'summary'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationApplier $translations,
        private readonly VersionRecorder $versions,
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('/admin/documents/nouveau', name: 'app_admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_CREATE, OfficialDocument::class);

        $data = new DocumentData();
        $data->translations = $this->translations->read(null, self::FIELD_NAMES);

        $form = $this->createForm(DocumentAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $document = new OfficialDocument();
            $this->fill($document, $data, true);
            $this->translations->apply($document, $data->translations, ['fr', 'en'], OfficialDocumentTranslation::class);
            $this->entityManager->persist($document);
            $this->entityManager->flush();
            $this->versions->record($document);
            $this->addFlash('success', 'admin.flash.created');

            return $this->redirectToRoute('app_admin_document_edit', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/document/form.html.twig', $this->context($form, null, $data, $request->getLocale()), $this->status($form));
    }

    #[Route('/admin/documents/{id}', name: 'app_admin_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $document = $this->entityManager->find(OfficialDocument::class, $id) ?? throw $this->createNotFoundException();
        $canEdit = $this->isGranted(Permission::CONTENT_EDIT, $document);
        if (!$canEdit && !$this->isGranted(Permission::CONTENT_TRANSLATE, $document)) {
            throw $this->createAccessDeniedException();
        }

        $data = new DocumentData();
        $data->type = $document->getType();
        $data->reference = $document->getReference();
        $data->documentDate = $document->getDocumentDate();
        $data->fileLanguage = $document->getFileLanguage();
        $data->fileRequired = null === $document->getFile();
        $data->translations = $this->translations->read($document, self::FIELD_NAMES);
        $data->editable = $canEdit ? ['fr', 'en'] : ['en'];

        $form = $this->createForm(DocumentAdminType::class, $data, ['details_disabled' => !$canEdit]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($canEdit) {
                $this->fill($document, $data, false);
            }
            $this->translations->apply($document, $data->translations, $data->editable, OfficialDocumentTranslation::class);
            $this->entityManager->flush();
            $this->versions->record($document);
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_document_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/document/form.html.twig', $this->context($form, $document, $data, $request->getLocale()), $this->status($form));
    }

    private function fill(OfficialDocument $document, DocumentData $data, bool $creating): void
    {
        $document->setType($data->type)->setReference('' === trim((string) $data->reference) ? null : trim((string) $data->reference))
            ->setDocumentDate($data->documentDate)->setFileLanguage($data->fileLanguage);

        if (null !== $data->file) {
            $previous = $document->getFile();
            $media = $this->storage->store($data->file);
            $this->entityManager->persist($media);
            $document->setFile($media);
            if (!$creating && null !== $previous) {
                $this->entityManager->flush();
                $this->storage->delete($previous);
                $this->entityManager->remove($previous);
            }
        }
    }

    /**
     * @param FormInterface<DocumentData> $form
     *
     * @return array<string, mixed>
     */
    private function context(FormInterface $form, ?OfficialDocument $document, DocumentData $data, string $locale): array
    {
        return [
            'form' => $form,
            'document' => $document,
            'tabs' => $this->translations->completeness($data->translations, self::FIELD_NAMES),
            'active' => \in_array('fr', $data->editable, true) ? $locale : 'en',
            'translateOnly' => ['en'] === $data->editable,
        ];
    }

    /**
     * @param FormInterface<DocumentData> $form
     */
    private function status(FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
