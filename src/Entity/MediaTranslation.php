<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TranslationTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Champs traduits de Media (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_media_translation_locale', columns: ['translatable_id', 'locale'])]
class MediaTranslation implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\ManyToOne(targetEntity: Media::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Media $translatable;

    /** Texte alternatif ('' uniquement pour une image décorative). */
    #[ORM\Column(length: 255)]
    private string $alt = '';

    /** Légende. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    public function getTranslatable(): Media
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof Media) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, Media::class));
        }

        $this->translatable = $translatable;

        return $this;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }
}
