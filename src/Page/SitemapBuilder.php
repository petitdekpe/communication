<?php

declare(strict_types=1);

namespace App\Page;

use App\Settings\SiteSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Entrées du plan du site, HTML et XML : accueil, rubriques fixes du portail puis pages de l'arborescence (parents avant
 * enfants). Pour chaque langue, l'entrée porte les adresses de toutes les versions linguistiques (hreflang).
 */
final class SitemapBuilder
{
    /** Rubriques fixes : clé de libellé (sitemap.section.*) => route. */
    public const array SECTIONS = [
        'services' => 'app_services_index',
        'news' => 'app_news_index',
        'press_releases' => 'app_press_releases',
        'documents' => 'app_documents',
        'agenda' => 'app_agenda',
        'data' => 'app_data',
        'consultations' => 'app_consultations',
        'media_library' => 'app_media_library',
    ];

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly PageTree $tree,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteSettings $settings,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * Rubriques fixes avec leur adresse dans la langue.
     *
     * @return list<array{key: string, url: string}>
     */
    public function sections(string $locale): array
    {
        $sections = [];
        foreach (self::SECTIONS as $key => $route) {
            if ('agenda' === $key && !$this->settings->agendaEnabled()) {
                continue;
            }
            $sections[] = ['key' => $key, 'url' => $this->urlGenerator->generate($route, ['_locale' => $locale])];
        }

        return $sections;
    }

    /**
     * Pages de l'arborescence (plan HTML) : titre, adresse et profondeur dans la langue.
     *
     * @return list<array{title: string, url: string, depth: int, lang: string|null}>
     */
    public function pages(string $locale): array
    {
        $pages = [];
        foreach ($this->tree->flatten() as ['page' => $page, 'depth' => $depth]) {
            $translation = $page->translate($locale);
            $pages[] = [
                'title' => $this->tree->title($page, $locale),
                'url' => $this->tree->url($page, $locale),
                'depth' => $depth,
                'lang' => $translation->getLocale() !== $locale ? $translation->getLocale() : null,
            ];
        }

        return $pages;
    }

    /**
     * Entrées du sitemap XML : une par langue et par adresse, avec les alternates de chaque version linguistique.
     *
     * @return list<array{loc: string, lastmod: \DateTimeImmutable|null, alternates: array<string, string>}>
     */
    public function entries(): array
    {
        $entries = [];

        $home = $this->tree->home();
        $entries = [...$entries, ...$this->entry(fn (string $locale): string => $this->urlGenerator->generate('app_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL), $home?->getUpdatedAt(), null)];
        foreach (self::SECTIONS as $route) {
            $entries = [...$entries, ...$this->entry(fn (string $locale): string => $this->urlGenerator->generate($route, ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL), null, null)];
        }
        $entries = [...$entries, ...$this->entry(fn (string $locale): string => $this->urlGenerator->generate('app_sitemap_html', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL), null, null)];

        foreach ($this->tree->flatten() as ['page' => $page]) {
            $entries = [...$entries, ...$this->entry(
                fn (string $locale): string => $this->tree->url($page, $locale, true),
                $page->getUpdatedAt(),
                static fn (string $locale): bool => $page->hasTranslation($locale),
            )];
        }

        return $entries;
    }

    /**
     * @param \Closure(string): string      $url       adresse absolue dans une langue
     * @param (\Closure(string): bool)|null $available la langue existe-t-elle pour ce contenu ? (null : toujours)
     *
     * @return list<array{loc: string, lastmod: \DateTimeImmutable|null, alternates: array<string, string>}>
     */
    private function entry(\Closure $url, ?\DateTimeImmutable $lastmod, ?\Closure $available): array
    {
        $locales = array_values(array_filter($this->locales, static fn (string $locale): bool => null === $available || $available($locale)));
        if ([] === $locales) {
            return [];
        }

        $alternates = [];
        foreach ($locales as $locale) {
            $alternates[$locale] = $url($locale);
        }

        $entries = [];
        foreach ($locales as $locale) {
            $entries[] = ['loc' => $alternates[$locale], 'lastmod' => $lastmod, 'alternates' => $alternates];
        }

        return $entries;
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
