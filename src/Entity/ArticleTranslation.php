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
 * Champs traduits de Article (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_article_translation_locale', columns: ['translatable_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_article_translation_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class ArticleTranslation implements SluggableInterface
{
    use TranslationTrait;
    use SlugTrait;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $translatable;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    /** Chapô. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    public function getTranslatable(): Article
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof Article) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, Article::class));
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

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;

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
}
