<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Média d'une galerie, à une position donnée.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_media_gallery_item', columns: ['gallery_id', 'media_id'])]
class MediaGalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MediaGallery::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaGallery $gallery;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Media $media;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(Media $media, int $position = 0)
    {
        $this->media = $media;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGallery(): MediaGallery
    {
        return $this->gallery;
    }

    public function setGallery(MediaGallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
