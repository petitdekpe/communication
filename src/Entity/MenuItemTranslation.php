<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TranslationTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs traduits de MenuItem (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_menu_item_translation_locale', columns: ['translatable_id', 'locale'])]
class MenuItemTranslation implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MenuItem $translatable;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $label = null;

    /** Chemin interne dans cette langue (/fr/…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    public function getTranslatable(): MenuItem
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof MenuItem) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, MenuItem::class));
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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }
}
