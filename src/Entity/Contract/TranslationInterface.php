<?php

declare(strict_types=1);

namespace App\Entity\Contract;

interface TranslationInterface
{
    public function getLocale(): string;

    public function setLocale(string $locale): static;

    public function getTranslatable(): TranslatableInterface;

    public function setTranslatable(TranslatableInterface $translatable): static;
}
