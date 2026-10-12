<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\KeyFigureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Chiffre clé. La source et la date des données sont obligatoires.
 *
 * @method KeyFigureTranslation      translate(string $locale)
 * @method KeyFigureTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: KeyFigureRepository::class)]
class KeyFigure implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Valeur numérique ; null tant qu'elle n'est pas validée. */
    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 4, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    /** Date de mise à jour des données. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dataDate = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<string, KeyFigureTranslation> */
    #[ORM\OneToMany(targetEntity: KeyFigureTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    /** Valeur en nombre (entier si elle n'a pas de décimales) ; null tant qu'elle n'est pas validée. */
    public function getNumericValue(): int|float|null
    {
        if (null === $this->value) {
            return null;
        }

        return str_contains($this->value, '.') && 0.0 !== fmod((float) $this->value, 1.0) ? (float) $this->value : (int) $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getDataDate(): ?\DateTimeImmutable
    {
        return $this->dataDate;
    }

    public function setDataDate(\DateTimeImmutable $dataDate): static
    {
        $this->dataDate = $dataDate;

        return $this;
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
        if (!$translation instanceof KeyFigureTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, KeyFigureTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof KeyFigureTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
