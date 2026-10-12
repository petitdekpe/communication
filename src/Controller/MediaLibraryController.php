<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaGalleryRepository;
use App\Repository\VideoRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Médiathèque : galeries photo (vignettes, visionneuse) et vidéos YouTube chargées au clic.
 */
final class MediaLibraryController extends AbstractController
{
    #[Route(path: ['fr' => '/mediatheque', 'en' => '/media-library'], name: 'app_media_library', methods: ['GET'])]
    public function index(MediaGalleryRepository $galleries, VideoRepository $videos, ClockInterface $clock): Response
    {
        $now = $clock->now();

        return $this->render('media_library/index.html.twig', [
            'galleries' => $galleries->findPublished($now),
            'videos' => $videos->findPublished($now),
        ]);
    }
}
