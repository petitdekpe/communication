<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\Contract\TranslationInterface;
use App\Enum\ArticleType;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<Article>
 */
final class ArticleFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Article::class;
    }

    public function ofType(ArticleType $type): static
    {
        return $this->with(['type' => $type]);
    }

    public function draft(): static
    {
        return $this->with(['status' => PublicationStatus::Draft, 'publishedAt' => null]);
    }

    protected function defaults(): array
    {
        return [
            'type' => ArticleType::Actualite,
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new ArticleTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Titre de l\'article', 'Article title', $number))
            ->setExcerpt(self::text($locale, 'Chapô de l\'article', 'Article standfirst'))
            ->setBody('<p>'.self::text($locale, 'Corps de l\'article', 'Article body').'</p>');
    }
}
