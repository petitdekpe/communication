<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rubrique de classement (articles, documents officiels).
 *
 * @method CategoryTranslation      translate(string $locale)
 * @method CategoryTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category implements TranslatableInterface, TimestampableInterface
{
    use TimestampableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<string, CategoryTranslation> */
    #[ORM\OneToMany(targetEntity: CategoryTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof CategoryTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, CategoryTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof CategoryTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
