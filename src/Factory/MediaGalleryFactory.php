<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\MediaGallery;
use App\Entity\MediaGalleryTranslation;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<MediaGallery>
 */
final class MediaGalleryFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return MediaGallery::class;
    }

    protected function defaults(): array
    {
        return [
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new MediaGalleryTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Titre de la galerie', 'Gallery title', $number))
            ->setDescription(self::text($locale, 'Description de la galerie', 'Gallery description'));
    }
}
