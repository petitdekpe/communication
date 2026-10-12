<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\SettingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètre du site (coordonnées, mentions…), traduisible.
 *
 * @method SettingTranslation      translate(string $locale)
 * @method SettingTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting implements TranslatableInterface, TimestampableInterface
{
    use TimestampableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    /** @var Collection<string, SettingTranslation> */
    #[ORM\OneToMany(targetEntity: SettingTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof SettingTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, SettingTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof SettingTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
