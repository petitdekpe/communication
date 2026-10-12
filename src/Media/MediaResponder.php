<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Media;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Réponse HTTP qui sert un fichier du stockage Flysystem : jamais d'accès direct au dossier de stockage.
 * Les images s'affichent dans la page, tout le reste est proposé en téléchargement.
 */
final class MediaResponder
{
    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function respond(Media $media, bool $forceDownload = false): StreamedResponse
    {
        $storage = $this->storage;
        $response = new StreamedResponse(static function () use ($storage, $media): void {
            $stream = $storage->readStream($media);
            fpassthru($stream);
            fclose($stream);
        });

        $mimeType = (string) $media->getMimeType();
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', (string) $media->getSize());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $inline = !$forceDownload && str_starts_with($mimeType, 'image/') && 'image/svg+xml' !== $mimeType;
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            (string) $media->getOriginalFilename(),
            preg_replace('/[^\x20-\x7e]/', '_', (string) $media->getOriginalFilename()) ?? 'fichier',
        ));
        // SVG : servi en téléchargement et sans script (jamais exécuté dans le contexte du site).
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:");

        return $response;
    }
}
