<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Enum\DocumentType;
use App\Repository\OfficialDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document officiel téléchargeable (texte, rapport…).
 *
 * @method OfficialDocumentTranslation      translate(string $locale)
 * @method OfficialDocumentTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: OfficialDocumentRepository::class)]
class OfficialDocument implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence officielle (numéro du texte). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 16, enumType: DocumentType::class)]
    private DocumentType $type = DocumentType::Report;

    /** Date de signature (ou d'adoption) du document. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $documentDate = null;

    /** Langue du fichier (code ISO 639-1) : peut différer de la langue de la page. */
    #[ORM\Column(length: 2)]
    #[Assert\Choice(choices: ['fr', 'en'])]
    private string $fileLanguage = 'fr';

    /** Nombre de téléchargements : incrémenté en SQL par OfficialDocumentRepository::incrementDownloadCount(). */
    #[ORM\Column(options: ['default' => 0])]
    private int $downloadCount = 0;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $file = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    /** @var Collection<string, OfficialDocumentTranslation> */
    #[ORM\OneToMany(targetEntity: OfficialDocumentTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFileLanguage(): string
    {
        return $this->fileLanguage;
    }

    public function setFileLanguage(string $fileLanguage): static
    {
        $this->fileLanguage = $fileLanguage;

        return $this;
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function getDocumentDate(): ?\DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?\DateTimeImmutable $documentDate): static
    {
        $this->documentDate = $documentDate;

        return $this;
    }

    public function getFile(): ?Media
    {
        return $this->file;
    }

    public function setFile(?Media $file): static
    {
        $this->file = $file;

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

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$translation instanceof OfficialDocumentTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, OfficialDocumentTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof OfficialDocumentTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
