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
 * Champs traduits de Consultation (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_consultation_translation_locale', columns: ['translatable_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_consultation_translation_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class ConsultationTranslation implements SluggableInterface
{
    use TranslationTrait;
    use SlugTrait;

    #[ORM\ManyToOne(targetEntity: Consultation::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Consultation $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    /** Texte de la page de résultats (HTML éditorial assaini à l'affichage). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $results = null;

    public function getTranslatable(): Consultation
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof Consultation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, Consultation::class));
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

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getSlugSource(): string
    {
        return (string) $this->title;
    }

    public function getResults(): ?string
    {
        return $this->results;
    }

    public function setResults(?string $results): static
    {
        $this->results = $results;

        return $this;
    }
}
