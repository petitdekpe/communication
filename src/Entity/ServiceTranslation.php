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
 * Champs traduits de Service (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_service_translation_locale', columns: ['translatable_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_service_translation_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class ServiceTranslation implements SluggableInterface
{
    use TranslationTrait;
    use SlugTrait;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Service $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    /** Coût tel qu'affiché (texte validé). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cost = null;

    /** Délai tel qu'affiché (texte validé). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $delay = null;

    /**
     * Étapes de la démarche, dans l'ordre.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $steps = [];

    /**
     * Pièces à fournir (une ligne par pièce).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $requiredDocuments = [];

    /** Coordonnées du service à contacter (texte libre validé). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contact = null;

    public function getTranslatable(): Service
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof Service) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, Service::class));
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

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): static
    {
        $this->cost = $cost;

        return $this;
    }

    public function getDelay(): ?string
    {
        return $this->delay;
    }

    public function setDelay(?string $delay): static
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @param array<string> $steps
     */
    public function setSteps(array $steps): static
    {
        $this->steps = array_values($steps);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRequiredDocuments(): array
    {
        return $this->requiredDocuments;
    }

    /**
     * @param array<string> $requiredDocuments
     */
    public function setRequiredDocuments(array $requiredDocuments): static
    {
        $this->requiredDocuments = array_values($requiredDocuments);

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getSlugSource(): string
    {
        return (string) $this->title;
    }
}
