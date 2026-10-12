<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert un fichier du stockage privé en téléchargement (jamais interprété par le navigateur, jamais mis en cache).
 * Le nom proposé est généré par l'application : le nom donné par l'usager n'est jamais repris.
 */
final class AttachmentResponder
{
    public function __construct(private readonly AttachmentStorage $storage)
    {
    }

    public function download(string $path, string $mimeType, string $baseName): Response
    {
        $storage = $this->storage;
        $response = new StreamedResponse(static function () use ($storage, $path): void {
            $stream = $storage->readStreamByPath($path);
            fpassthru($stream);
            fclose($stream);
        });
        $filename = \sprintf('%s.%s', $baseName, 'application/pdf' === $mimeType ? 'pdf' : 'jpg');
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $filename));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
