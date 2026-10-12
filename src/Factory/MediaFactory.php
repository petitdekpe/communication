<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Media;
use App\Entity\MediaTranslation;
use App\Media\MediaStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Médias de démonstration, enregistrés via le vrai stockage Flysystem (fixtures/files/*).
 *
 * @extends PersistentObjectFactory<Media>
 */
final class MediaFactory extends PersistentObjectFactory
{
    public function __construct(
        private readonly MediaStorage $storage,
        #[Autowire('%kernel.project_dir%/fixtures/files')]
        private readonly string $filesDir,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return Media::class;
    }

    public function image(): static
    {
        return $this->with(['file' => 'photo-a-valider.svg']);
    }

    /** Photo raster (JPEG) : seule une image raster a des vignettes LiipImagine. */
    public function photo(): static
    {
        return $this->with(['file' => 'photo-a-valider.jpg']);
    }

    public function pdf(): static
    {
        return $this->with(['file' => 'document-a-valider.pdf']);
    }

    protected function defaults(): array
    {
        return ['file' => 'photo-a-valider.svg'];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::use(function (string $file): Media {
            $media = $this->storage->store(new \SplFileInfo($this->filesDir.'/'.$file));
            $media->setCredit(TranslatableFactory::MARKER.' Crédit');

            foreach (['fr' => ['Texte alternatif', 'Légende'], 'en' => ['Alternative text', 'Caption']] as $locale => [$alt, $caption]) {
                $media->addTranslation(
                    (new MediaTranslation())
                        ->setLocale($locale)
                        ->setAlt(TranslatableFactory::MARKER.' '.$alt)
                        ->setCaption(TranslatableFactory::MARKER.' '.$caption),
                );
            }

            return $media;
        })->disableHydration());
    }
}
