<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\ConsultationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Consultation citoyenne.
 *
 * @method ConsultationTranslation      translate(string $locale)
 * @method ConsultationTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $participationUrl = null;

    /**
     * Documents de la consultation (projet de texte, note de cadrage…).
     *
     * @var Collection<int, OfficialDocument>
     */
    #[ORM\ManyToMany(targetEntity: OfficialDocument::class)]
    #[ORM\JoinTable(name: 'consultation_document')]
    private Collection $documents;

    /** Page de résultats publiée : tant que l'administration ne la publie pas, elle reste invisible. */
    #[ORM\Column(options: ['default' => false])]
    private bool $resultsPublished = false;

    /** @var Collection<string, ConsultationTranslation> */
    #[ORM\OneToMany(targetEntity: ConsultationTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(\DateTimeImmutable $opensAt): static
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(\DateTimeImmutable $closesAt): static
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function getParticipationUrl(): ?string
    {
        return $this->participationUrl;
    }

    public function setParticipationUrl(?string $participationUrl): static
    {
        $this->participationUrl = $participationUrl;

        return $this;
    }

    /**
     * @return Collection<int, OfficialDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(OfficialDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }

        return $this;
    }

    public function removeDocument(OfficialDocument $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    public function isResultsPublished(): bool
    {
        return $this->resultsPublished;
    }

    public function setResultsPublished(bool $resultsPublished): static
    {
        $this->resultsPublished = $resultsPublished;

        return $this;
    }

    /** Consultation ouverte aux contributions à cet instant. */
    public function isOpen(\DateTimeImmutable $now): bool
    {
        return null !== $this->opensAt && null !== $this->closesAt && $this->opensAt <= $now && $now < $this->closesAt;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof ConsultationTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, ConsultationTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof ConsultationTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
