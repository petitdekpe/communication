<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Trait\PublishableTrait;
use App\Entity\Trait\TranslatableTrait;
use App\Repository\VideoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Vidéo hébergée sur YouTube, intégrée en mode « youtube-nocookie » et chargée au clic (médiathèque).
 *
 * @method VideoTranslation      translate(string $locale)
 * @method VideoTranslation|null getTranslation(string $locale)
 */
#[ORM\Entity(repositoryClass: VideoRepository::class)]
class Video implements TranslatableInterface, PublishableInterface
{
    use PublishableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant YouTube : 11 caractères (lettres, chiffres, « - » et « _ »). Jamais une URL entière. */
    #[ORM\Column(length: 11)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9_-]{11}$/', message: 'Identifiant YouTube invalide (11 caractères).')]
    private ?string $youtubeId = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<string, VideoTranslation> */
    #[ORM\OneToMany(targetEntity: VideoTranslation::class, mappedBy: 'translatable', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYoutubeId(): ?string
    {
        return $this->youtubeId;
    }

    public function setYoutubeId(string $youtubeId): static
    {
        $this->youtubeId = $youtubeId;

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
        if (!$translation instanceof VideoTranslation) {
            throw new \InvalidArgumentException(\sprintf('%s attend une traduction %s.', self::class, VideoTranslation::class));
        }

        if ($this->canAttachTranslation($translation)) {
            $translation->setTranslatable($this);
            $this->translations->set($translation->getLocale(), $translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        if ($translation instanceof VideoTranslation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }
}
