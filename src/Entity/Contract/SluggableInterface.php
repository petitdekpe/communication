<?php

declare(strict_types=1);

namespace App\Entity\Contract;

/**
 * Traduction portant un slug, unique par langue. Le slug est généré depuis getSlugSource()
 * s'il est vide (voir App\Doctrine\SlugListener), puis ne change plus : les URL restent stables.
 */
interface SluggableInterface extends TranslationInterface
{
    public function getSlug(): ?string;

    public function setSlug(?string $slug): static;

    public function getSlugSource(): string;
}
