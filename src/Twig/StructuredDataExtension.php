<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Article;
use App\Seo\StructuredData;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Les fonctions renvoient du JSON déjà échappé pour une balise <script type="application/ld+json">
 * (« < » et « & » encodés) : sûr à afficher tel quel.
 */
final class StructuredDataExtension
{
    public function __construct(
        private readonly StructuredData $structuredData,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction('schema_organization', isSafe: ['html'])]
    public function organization(): string
    {
        return $this->structuredData->toJson($this->structuredData->organization($this->locale()));
    }

    #[AsTwigFunction('schema_news_article', isSafe: ['html'])]
    public function newsArticle(Article $article): string
    {
        return $this->structuredData->toJson($this->structuredData->newsArticle($article, $this->locale()));
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }
}
