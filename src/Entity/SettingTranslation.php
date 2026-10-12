<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\TranslationTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Champs traduits de Setting (une ligne par langue).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_setting_translation_locale', columns: ['translatable_id', 'locale'])]
class SettingTranslation implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\ManyToOne(targetEntity: Setting::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Setting $translatable;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    public function getTranslatable(): Setting
    {
        return $this->translatable;
    }

    public function setTranslatable(TranslatableInterface $translatable): static
    {
        if (!$translatable instanceof Setting) {
            throw new \InvalidArgumentException(\sprintf('%s attend une entité %s.', self::class, Setting::class));
        }

        $this->translatable = $translatable;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }
}
