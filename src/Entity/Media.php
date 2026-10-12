<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\MediaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fichier stocké via Flysystem (stockage « media.storage »). Le texte alternatif et la légende sont traduits.
 *
 * @method MediaTranslation      translate(string $locale)
 * @method MediaTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media implements TranslatableInterface, TimestampableInterface
{
    use TimestampableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé du fichier dans le stockage Flysystem. */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $path = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 127)]
    private ?string $mimeType = null;

    /** Poids en octets. */
    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $credit = null;

    /** @var Collection<string, MediaTranslation> */
    #[ORM\OneToMany(targetEntity: MediaTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(?string $credit): static
    {
        $this->credit = $credit;

        return $this;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof MediaTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, MediaTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof MediaTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
