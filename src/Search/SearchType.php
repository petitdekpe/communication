<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\OfficialDocument;
use App\Entity\OfficialDocumentTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Service;
use App\Entity\ServiceTranslation;

/**
 * Types de contenu interrogés par la recherche globale, dans l'ordre d'affichage des groupes.
 */
enum SearchType: string
{
    case Article = 'article';
    case Document = 'document';
    case Service = 'service';
    case Event = 'event';
    case Page = 'page';

    public function labelKey(): string
    {
        return 'search.type.'.$this->value;
    }

    /**
     * @return class-string
     */
    public function entity(): string
    {
        return match ($this) {
            self::Article => Article::class,
            self::Document => OfficialDocument::class,
            self::Service => Service::class,
            self::Event => Event::class,
            self::Page => Page::class,
        };
    }

    /**
     * @return class-string
     */
    public function translationEntity(): string
    {
        return match ($this) {
            self::Article => ArticleTranslation::class,
            self::Document => OfficialDocumentTranslation::class,
            self::Service => ServiceTranslation::class,
            self::Event => EventTranslation::class,
            self::Page => PageTranslation::class,
        };
    }

    /**
     * Champs texte de la traduction dans lesquels on cherche.
     *
     * @return list<string>
     */
    public function searchFields(): array
    {
        return match ($this) {
            self::Article => ['title', 'excerpt', 'body'],
            self::Document => ['title', 'summary'],
            self::Service => ['title', 'summary', 'body'],
            self::Event => ['title', 'description', 'location'],
            self::Page => ['title', 'body'],
        };
    }

    /** Champ de la traduction affiché comme résumé sous le titre. */
    public function snippetField(): string
    {
        return match ($this) {
            self::Article => 'excerpt',
            self::Document, self::Service => 'summary',
            self::Event => 'description',
            self::Page => 'metaDescription',
        };
    }

    /** Champ de date de l'entité, pour trier du plus récent au plus ancien. */
    public function dateField(): string
    {
        return match ($this) {
            self::Event => 'startsAt',
            self::Document => 'documentDate',
            default => 'publishedAt',
        };
    }
}
