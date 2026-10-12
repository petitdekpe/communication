<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Article : actualité, communiqué, dossier ou avis officiel.
 *
 * @method ArticleTranslation      translate(string $locale)
 * @method ArticleTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: ArticleType::class)]
    private ArticleType $type = ArticleType::Actualite;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverImage = null;

    /** @var Collection<int, OfficialDocument> pièces jointes (documents officiels liés) */
    #[ORM\ManyToMany(targetEntity: OfficialDocument::class)]
    #[ORM\JoinTable(name: 'article_attachment')]
    private Collection $attachments;

    /** Galerie de médias optionnelle. */
    #[ORM\ManyToOne(targetEntity: MediaGallery::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MediaGallery $gallery = null;

    /** Mis en avant (« À la une »). */
    #[ORM\Column]
    private bool $featured = false;

    /** @var Collection<string, ArticleTranslation> */
    #[ORM\OneToMany(targetEntity: ArticleTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ArticleType
    {
        return $this->type;
    }

    public function setType(ArticleType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getCoverImage(): ?Media
    {
        return $this->coverImage;
    }

    public function setCoverImage(?Media $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
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

    /**
     * @return Collection<int, OfficialDocument>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(OfficialDocument $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    public function removeAttachment(OfficialDocument $attachment): static
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    /**
     * Pièces jointes publiées et munies d'un fichier.
     *
     * @return list<OfficialDocument>
     */
    public function getPublishedAttachments(?\DateTimeImmutable $now = null): array
    {
        return array_values($this->attachments->filter(
            static fn (OfficialDocument $document): bool => $document->isPublished($now) && null !== $document->getFile(),
        )->toArray());
    }

    /** Premier PDF joint (téléchargement proposé sur les communiqués). */
    public function getAttachedPdf(?\DateTimeImmutable $now = null): ?OfficialDocument
    {
        foreach ($this->getPublishedAttachments($now) as $document) {
            if ('application/pdf' === $document->getFile()?->getMimeType()) {
                return $document;
            }
        }

        return null;
    }

    public function getGallery(): ?MediaGallery
    {
        return $this->gallery;
    }

    public function setGallery(?MediaGallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof ArticleTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, ArticleTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof ArticleTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
