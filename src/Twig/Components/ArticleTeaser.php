<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Article;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Carte d'un article de la base : adapte l'entité aux props d'ArticleCard (titre dans la langue de la
 * page ou en français en cas de repli, image de couverture, catégorie éditoriale).
 */
#[AsTwigComponent]
final class ArticleTeaser
{
    public Article $article;
    public bool $featured = false;
    public int $headingLevel = 3;
}
