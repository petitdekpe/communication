<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Page de contenu libre, organisée en arborescence.
 *
 * @method PageTranslation      translate(string $locale)
 * @method PageTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
class Page implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    public const string HOME = 'home';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Page $parent = null;

    #[ORM\Column]
    private int $position = 0;

    /** Page système unique (« home » : accueil), repérée par cette clé plutôt que par son chemin ; null pour une page ordinaire. */
    #[ORM\Column(length: 32, nullable: true, unique: true)]
    private ?string $systemKey = null;

    /** @var Collection<string, PageTranslation> */
    #[ORM\OneToMany(targetEntity: PageTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Page
    {
        return $this->parent;
    }

    public function setParent(?Page $parent): static
    {
        $this->parent = $parent;

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

    public function getSystemKey(): ?string
    {
        return $this->systemKey;
    }

    public function setSystemKey(?string $systemKey): static
    {
        $this->systemKey = $systemKey;

        return $this;
    }

    public function isHome(): bool
    {
        return self::HOME === $this->systemKey;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof PageTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, PageTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof PageTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
