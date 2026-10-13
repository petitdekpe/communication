<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\MediaGallery;
use App\Entity\MediaGalleryTranslation;
use App\Entity\OfficialDocument;
use App\Entity\OfficialDocumentTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Video;
use App\Entity\VideoTranslation;

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
    case Gallery = 'gallery';
    case Video = 'video';

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
            self::Gallery => MediaGallery::class,
            self::Video => Video::class,
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
            self::Gallery => MediaGalleryTranslation::class,
            self::Video => VideoTranslation::class,
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
            self::Gallery, self::Video => ['title', 'description'],
        };
    }

    /** Champ de la traduction affiché comme résumé sous le titre. */
    public function snippetField(): string
    {
        return match ($this) {
            self::Article => 'excerpt',
            self::Document, self::Service => 'summary',
            self::Event, self::Gallery, self::Video => 'description',
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
