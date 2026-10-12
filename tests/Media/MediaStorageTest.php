<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\MediaStorage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MediaStorageTest extends KernelTestCase
{
    private const string FILES = __DIR__.'/../../fixtures/files';

    public function testStoresAFileThroughFlysystemAndDescribesIt(): void
    {
        $storage = self::getContainer()->get(MediaStorage::class);

        $media = $storage->store(new \SplFileInfo(self::FILES.'/document-a-valider.pdf'), 'Rapport annuel 2026.pdf');

        self::assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-f]{16}-rapport-annuel-2026\.pdf$#', (string) $media->getPath());
        self::assertSame('Rapport annuel 2026.pdf', $media->getOriginalFilename());
        self::assertSame('application/pdf', $media->getMimeType());
        self::assertSame(filesize(self::FILES.'/document-a-valider.pdf'), $media->getSize());
        self::assertNull($media->getWidth());

        self::assertTrue($storage->exists($media));
        self::assertSame(file_get_contents(self::FILES.'/document-a-valider.pdf'), stream_get_contents($storage->readStream($media)));

        $storage->delete($media);
        self::assertFalse($storage->exists($media));
    }

    public function testReadsImageDimensions(): void
    {
        $media = self::getContainer()->get(MediaStorage::class)->store(new \SplFileInfo(self::FILES.'/photo-a-valider.svg'));

        self::assertSame('image/svg+xml', $media->getMimeType());
        self::assertSame([640, 360], [$media->getWidth(), $media->getHeight()]);
    }
}
