<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mot-clé libre.
 *
 * @method TagTranslation      translate(string $locale)
 * @method TagTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag implements TranslatableInterface, TimestampableInterface
{
    use TimestampableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<string, TagTranslation> */
    #[ORM\OneToMany(targetEntity: TagTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof TagTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, TagTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof TagTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
