<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Media;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Enregistre les fichiers dans le stockage Flysystem « media.storage » (adaptateur local, monté sur
 * le NFS en production) et crée l'entité Media correspondante (type MIME, poids, dimensions).
 * Clé de stockage : « 2026/10/3f2a9c1e5b7d4a60-nom-du-fichier.pdf ».
 */
final class MediaStorage
{
    public function __construct(
        private readonly FilesystemOperator $mediaStorage,
        private readonly ClockInterface $clock,
    ) {
    }

    public function store(\SplFileInfo $file, ?string $originalFilename = null): Media
    {
        $path = $file->getPathname();
        $originalFilename ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $mimeType = MimeTypes::getDefault()->guessMimeType($path) ?? 'application/octet-stream';
        $extension = strtolower(pathinfo($originalFilename, \PATHINFO_EXTENSION));
        if ('' === $extension) {
            $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin';
        }
        $name = (new AsciiSlugger())->slug(pathinfo($originalFilename, \PATHINFO_FILENAME))->lower()->truncate(60, cut: false)->toString();

        $key = \sprintf('%s/%s-%s.%s', $this->clock->now()->format('Y/m'), bin2hex(random_bytes(8)), '' === $name ? 'fichier' : $name, $extension);

        $stream = fopen($path, 'r');
        if (false === $stream) {
            throw new \RuntimeException(\sprintf('Lecture impossible : %s', $path));
        }
        try {
            $this->mediaStorage->writeStream($key, $stream);
        } finally {
            fclose($stream);
        }

        [$width, $height] = $this->dimensions($path, $mimeType);

        return (new Media())
            ->setPath($key)
            ->setOriginalFilename($originalFilename)
            ->setMimeType($mimeType)
            ->setSize((int) filesize($path))
            ->setWidth($width)
            ->setHeight($height);
    }

    /**
     * @return resource
     */
    public function readStream(Media $media)
    {
        return $this->mediaStorage->readStream((string) $media->getPath());
    }

    public function exists(Media $media): bool
    {
        return $this->mediaStorage->fileExists((string) $media->getPath());
    }

    public function delete(Media $media): void
    {
        $this->mediaStorage->delete((string) $media->getPath());
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $path, string $mimeType): array
    {
        if ('image/svg+xml' === $mimeType) {
            $svg = (string) file_get_contents($path, length: 4096);
            if (1 === preg_match('/<svg\b[^>]*\bwidth="(\d+)(?:px)?"[^>]*\bheight="(\d+)(?:px)?"/i', $svg, $matches)) {
                return [(int) $matches[1], (int) $matches[2]];
            }

            return [null, null];
        }

        if (str_starts_with($mimeType, 'image/')) {
            $size = @getimagesize($path);
            if (false !== $size) {
                return [$size[0], $size[1]];
            }
        }

        return [null, null];
    }
}
