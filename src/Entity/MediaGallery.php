<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\MediaGalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Galerie de médias (médiathèque de l'espace presse).
 *
 * @method MediaGalleryTranslation      translate(string $locale)
 * @method MediaGalleryTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: MediaGalleryRepository::class)]
class MediaGallery implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, MediaGalleryItem> */
    #[ORM\OneToMany(targetEntity: MediaGalleryItem::class, mappedBy: 'gallery', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /** @var Collection<string, MediaGalleryTranslation> */
    #[ORM\OneToMany(targetEntity: MediaGalleryTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, MediaGalleryItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(MediaGalleryItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setGallery($this);
        }

        return $this;
    }

    public function removeItem(MediaGalleryItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof MediaGalleryTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, MediaGalleryTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof MediaGalleryTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
