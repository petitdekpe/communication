<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Video;
use App\Entity\VideoTranslation;
use App\Enum\PublicationStatus;

/**
 * Vidéos de démonstration : l'identifiant « AVALIDER001 » n'est pas celui d'une vidéo réelle.
 *
 * @extends TranslatableFactory<Video>
 */
final class VideoFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Video::class;
    }

    protected function defaults(): array
    {
        return [
            'youtubeId' => 'AVALIDER001',
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new VideoTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Titre de la vidéo', 'Video title', $number))
            ->setDescription(self::text($locale, 'Description de la vidéo', 'Video description'));
    }
}
