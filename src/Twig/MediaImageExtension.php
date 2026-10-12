<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Media;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Twig\Attribute\AsTwigFunction;

/**
 * Adresse d'une image redimensionnée par LiipImagine (filtres « thumb », « viewer »). Les fichiers qui ne sont pas
 * des images raster (SVG, PDF) sont servis tels quels : GD ne sait pas les traiter.
 */
final class MediaImageExtension
{
    private const array RASTER = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly CacheManager $imagine,
        private readonly ContentExtension $content,
    ) {
    }

    #[AsTwigFunction('media_image')]
    public function mediaImage(Media $media, string $filter): string
    {
        if (!\in_array($media->getMimeType(), self::RASTER, true)) {
            return $this->content->mediaUrl($media);
        }

        return $this->imagine->getBrowserPath((string) $media->getPath(), $filter);
    }
}
