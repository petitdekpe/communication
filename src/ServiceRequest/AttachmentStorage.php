<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestAttachment;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Enregistre les pièces jointes dans le stockage « private.storage », monté hors de la racine web
 * (aucune URL ne les expose). Clé : « 2026/10/3f2a9c1e5b7d4a603f2a9c1e5b7d4a60.pdf », entièrement
 * aléatoire : le nom donné par l'usager n'entre jamais dans un chemin.
 *
 * La validation du formulaire a déjà contrôlé les fichiers ; ce service les contrôle une seconde fois
 * (type détecté sur le contenu, signature, poids) car il est la dernière étape avant l'écriture sur le disque.
 */
final class AttachmentStorage
{
    public function __construct(
        private readonly FilesystemOperator $privateStorage,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws \UnexpectedValueException si le fichier n'est pas un PDF ou un JPEG de 5 Mo au plus
     */
    public function store(ServiceRequest $request, UploadedFile $file): ServiceRequestAttachment
    {
        $stored = $this->put($file);

        return new ServiceRequestAttachment($request, $stored->path, $stored->originalName, $stored->mimeType, $stored->size);
    }

    /**
     * Dépose un fichier contrôlé dans le stockage privé (utilisé aussi par les contributions aux consultations).
     *
     * @throws \UnexpectedValueException si le fichier n'est pas un PDF ou un JPEG de 5 Mo au plus
     */
    public function put(UploadedFile $file): StoredFile
    {
        $path = $file->getPathname();
        $size = (int) filesize($path);
        $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        if (null === $mimeType || !isset(AllowedAttachment::MIME_TYPES[$mimeType]) || $size > AllowedAttachment::MAX_BYTES || !$this->hasExpectedSignature($path, $mimeType)) {
            throw new \UnexpectedValueException('Pièce jointe refusée : seuls les PDF et JPEG de 5 Mo au plus sont acceptés.');
        }

        $key = \sprintf('%s/%s.%s', $this->clock->now()->format('Y/m'), bin2hex(random_bytes(16)), AllowedAttachment::MIME_TYPES[$mimeType]);

        $stream = fopen($path, 'r');
        if (false === $stream) {
            throw new \RuntimeException('Lecture de la pièce jointe impossible.');
        }
        try {
            $this->privateStorage->writeStream($key, $stream);
        } finally {
            fclose($stream);
        }

        return new StoredFile($key, $this->displayName($file), $mimeType, $size);
    }

    public function delete(ServiceRequestAttachment $attachment): void
    {
        $this->deletePath($attachment->getPath());
    }

    public function deletePath(string $path): void
    {
        $this->privateStorage->delete($path);
    }

    public function exists(ServiceRequestAttachment $attachment): bool
    {
        return $this->existsByPath($attachment->getPath());
    }

    public function existsByPath(string $path): bool
    {
        return $this->privateStorage->fileExists($path);
    }

    /**
     * @return resource
     */
    public function readStream(ServiceRequestAttachment $attachment)
    {
        return $this->readStreamByPath($attachment->getPath());
    }

    /**
     * @return resource
     */
    public function readStreamByPath(string $path)
    {
        return $this->privateStorage->readStream($path);
    }

    /**
     * Signature du format : « %PDF- » en tête de fichier ; JPEG décodable en tant que tel.
     */
    private function hasExpectedSignature(string $path, string $mimeType): bool
    {
        if ('application/pdf' === $mimeType) {
            return '%PDF-' === file_get_contents($path, length: 5);
        }

        $info = @getimagesize($path);

        return false !== $info && \IMAGETYPE_JPEG === $info[2];
    }

    /**
     * Nom d'origine, conservé pour information : sans chemin, sans caractère de contrôle, tronqué.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return '' === $name ? 'fichier' : mb_substr($name, 0, 120);
    }
}
