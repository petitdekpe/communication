<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TranslationTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs traduits de KeyFigure (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_key_figure_translation_locale', columns: ['translatable_id', 'locale'])]
class KeyFigureTranslation implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\ManyToOne(targetEntity: KeyFigure::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private KeyFigure $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $source = null;

    public function getTranslatable(): KeyFigure
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof KeyFigure) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, KeyFigure::class));
        }

        $this->translatable = $translatable;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }
}
