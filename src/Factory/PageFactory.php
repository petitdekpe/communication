<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<Page>
 */
final class PageFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    protected function defaults(): array
    {
        return [
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new PageTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Titre de la page', 'Page title', $number))
            ->setBody(self::text($locale, 'Contenu de la page', 'Page content'))
            ->setMetaDescription(self::text($locale, 'Description pour les moteurs de recherche', 'Search engine description'));
    }
}
