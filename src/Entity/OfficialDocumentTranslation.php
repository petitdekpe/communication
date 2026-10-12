<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\SluggableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Trait\SlugTrait;
use App\Entity\Trait\TranslationTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs traduits de OfficialDocument (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\Index(name: 'ft_official_document_translation', columns: ['title', 'summary'], flags: ['fulltext'])]
#[ORM\UniqueConstraint(name: 'uniq_official_document_translation_locale', columns: ['translatable_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_official_document_translation_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class OfficialDocumentTranslation implements SluggableInterface
{
    use TranslationTrait;
    use SlugTrait;

    #[ORM\ManyToOne(targetEntity: OfficialDocument::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OfficialDocument $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    public function getTranslatable(): OfficialDocument
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof OfficialDocument) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, OfficialDocument::class));
        }

        $this->translatable = $translatable;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSlugSource(): string
    {
        return (string) $this->title;
    }
}
