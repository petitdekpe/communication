<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Enum\Audience;
use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Démarche (service en ligne ou au guichet).
 *
 * @method ServiceTranslation      translate(string $locale)
 * @method ServiceTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: Audience::class)]
    private Audience $audience = Audience::Citoyens;

    /** Démarche réalisable en ligne. */
    #[ORM\Column]
    private bool $online = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $onlineUrl = null;

    /** Clé du formulaire interne (voir App\ServiceRequest\ServiceFormRegistry) ; vide si la démarche n'en a pas. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $formKey = null;

    /** Texte officiel qui fonde la démarche. */
    #[ORM\ManyToOne(targetEntity: OfficialDocument::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?OfficialDocument $legalBasis = null;

    /** Service prioritaire (page d'accueil). */
    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<string, ServiceTranslation> */
    #[ORM\OneToMany(targetEntity: ServiceTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAudience(): Audience
    {
        return $this->audience;
    }

    public function setAudience(Audience $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): static
    {
        $this->online = $online;

        return $this;
    }

    public function getOnlineUrl(): ?string
    {
        return $this->onlineUrl;
    }

    public function setOnlineUrl(?string $onlineUrl): static
    {
        $this->onlineUrl = $onlineUrl;

        return $this;
    }

    public function getFormKey(): ?string
    {
        return $this->formKey;
    }

    public function setFormKey(?string $formKey): static
    {
        $this->formKey = $formKey;

        return $this;
    }

    public function getLegalBasis(): ?OfficialDocument
    {
        return $this->legalBasis;
    }

    public function setLegalBasis(?OfficialDocument $legalBasis): static
    {
        $this->legalBasis = $legalBasis;

        return $this;
    }

    /** Démarche réalisable en ligne par le formulaire interne du portail. */
    public function hasInternalForm(): bool
    {
        return $this->online && null !== $this->formKey;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

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
        if (!$translation instanceof ServiceTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, ServiceTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof ServiceTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
