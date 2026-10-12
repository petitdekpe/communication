<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Article;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Données structurées schema.org (JSON-LD) : GovernmentOrganization et NewsArticle.
 * Seules des informations déjà publiées sur le site sont exposées (pas de coordonnées à valider).
 */
final class StructuredData
{
    private const int JSON_FLAGS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            'name' => $this->translator->trans('site.name', locale: $locale),
            'url' => $this->urlGenerator->generate('app_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            'areaServed' => ['@type' => 'Country', 'name' => 'Bénin'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function newsArticle(Article $article, string $locale): array
    {
        $translation = $article->translate($locale);
        $url = $this->urlGenerator->generate('app_article_show', ['slug' => $translation->getSlug(), '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL);
        $organization = $this->organization($locale);
        unset($organization['@context']);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $translation->getTitle(),
            'inLanguage' => $translation->getLocale(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
            'datePublished' => $article->getPublishedAt()?->format(\DATE_ATOM),
            'dateModified' => ($article->getUpdatedAt() ?? $article->getPublishedAt())?->format(\DATE_ATOM),
            'description' => $translation->getExcerpt(),
            'author' => $organization,
            'publisher' => $organization,
        ];

        $cover = $article->getCoverImage();
        if (null !== $cover) {
            $data['image'] = [$this->urlGenerator->generate(
                'app_media',
                ['id' => $cover->getId(), 'filename' => basename((string) $cover->getPath()), '_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )];
        }

        return array_filter($data, static fn (mixed $value): bool => null !== $value && '' !== $value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function toJson(array $data): string
    {
        return json_encode($data, self::JSON_FLAGS);
    }
}
