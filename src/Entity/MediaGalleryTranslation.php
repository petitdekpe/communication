<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\SluggableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Trait\SlugTrait;
use App\Entity\Trait\TranslationTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs traduits de MediaGallery (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_media_gallery_translation_locale', columns: ['translatable_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_media_gallery_translation_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class MediaGalleryTranslation implements SluggableInterface
{
    use TranslationTrait;
    use SlugTrait;

    #[ORM\ManyToOne(targetEntity: MediaGallery::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaGallery $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getTranslatable(): MediaGallery
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof MediaGallery) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, MediaGallery::class));
        }

        $this->translatable = $translatable;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSlugSource(): string
    {
        return (string) $this->title;
    }
}
