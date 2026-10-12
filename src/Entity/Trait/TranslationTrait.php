<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs communs des entités de traduction (XxxTranslation). L'entité déclare la relation vers son
 * entité traduisible (propriété $translatable, ManyToOne inversedBy: 'translations') et les contraintes
 * d'unicité : (translatable_id, locale) et, si elle a un slug, (locale, slug).
 */
trait TranslationTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    #[Assert\Choice(choices: ['fr', 'en'])]
    private string $locale;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}
