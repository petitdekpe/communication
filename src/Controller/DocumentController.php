<?php

declare(strict_types=1);

namespace App\Controller;

use App\Document\DocumentListingService;
use App\Enum\DocumentType;
use App\Media\MediaResponder;
use App\Media\MediaStorage;
use App\Repository\OfficialDocumentRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentController extends AbstractController
{
    #[Route(path: ['fr' => '/documents', 'en' => '/documents'], name: 'app_documents', methods: ['GET'])]
    public function index(Request $request, DocumentListingService $listings): Response
    {
        $listing = $listings->listing($request->query->all(), $request->getLocale());
        if ($listing->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('documents/index.html.twig', ['listing' => $listing, 'types' => DocumentType::cases()]);
    }

    /**
     * Sert le fichier depuis Flysystem (jamais d'accès direct au dossier de stockage) et compte le téléchargement.
     * Seul un GET compte : une requête HEAD (aperçu, vérification de lien) n'est pas un téléchargement.
     */
    #[Route(
        path: ['fr' => '/documents/{id}/telechargement', 'en' => '/documents/{id}/download'],
        name: 'app_document_download',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function download(
        int $id,
        Request $request,
        OfficialDocumentRepository $documents,
        MediaStorage $storage,
        MediaResponder $responder,
        ClockInterface $clock,
    ): Response {
        $document = $documents->findPublished($id, $clock->now()) ?? throw $this->createNotFoundException();
        $file = $document->getFile();
        if (null === $file || !$storage->exists($file)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod(Request::METHOD_GET)) {
            $documents->incrementDownloadCount($document);
        }

        $response = $responder->respond($file, forceDownload: true);
        // Jamais servi par un cache partagé : chaque téléchargement doit atteindre l'application pour être compté.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
