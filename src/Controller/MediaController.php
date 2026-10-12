<?php

declare(strict_types=1);

namespace App\Controller;

use App\Media\MediaResponder;
use App\Media\MediaStorage;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert les fichiers du stockage Flysystem. Les images s'affichent dans la page ; les autres fichiers
 * (PDF, documents…) sont proposés en téléchargement. Le nom dans l'URL n'est qu'indicatif.
 */
final class MediaController extends AbstractController
{
    #[Route(
        path: ['fr' => '/medias/{id}/{filename}', 'en' => '/media/{id}/{filename}'],
        name: 'app_media',
        requirements: ['id' => '\d+', 'filename' => '[A-Za-z0-9._-]+'],
        methods: ['GET'],
    )]
    public function __invoke(int $id, MediaRepository $medias, MediaStorage $storage, MediaResponder $responder): Response
    {
        $media = $medias->find($id) ?? throw $this->createNotFoundException();
        if (!$storage->exists($media)) {
            throw $this->createNotFoundException();
        }

        $response = $responder->respond($media);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
