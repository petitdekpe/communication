<?php

declare(strict_types=1);

namespace App\Routing;

use App\Entity\Contract\SluggableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Page;
use App\Page\PageTree;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * URL équivalente de la page courante dans chaque langue (sélecteur FR / EN, balises hreflang).
 *
 * - Même route, même paramètres et même query string, seule la langue change.
 * - Pages de contenu : le contrôleur dépose l'entité traduisible dans l'attribut de requête
 *   TRANSLATABLE_ATTRIBUTE ; le paramètre {slug} est alors remplacé par le slug de la langue cible
 *   (ou le slug français si le contenu n'est pas traduit : la page cible affichera le repli).
 * - Page de l'arborescence (route app_page_show) : le chemin {path} est celui de la page dans la langue cible.
 * - Page sans route (erreur) : accueil de chaque langue.
 */
final class AlternateUrlResolver
{
    public const string TRANSLATABLE_ATTRIBUTE = '_translatable';

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PageTree $pageTree,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    /**
     * @return array<string, string> langue => URL
     */
    public function resolve(?Request $request): array
    {
        $route = $request?->attributes->get('_canonical_route') ?? $request?->attributes->get('_route');
        $params = (array) ($request?->attributes->get('_route_params') ?? []);
        unset($params['_canonical_route']);
        $query = $request?->query->all() ?? [];
        $translatable = $request?->attributes->get(self::TRANSLATABLE_ATTRIBUTE);

        $urls = [];
        foreach ($this->locales as $locale) {
            $urls[$locale] = $this->urlFor($locale, \is_string($route) ? $route : null, $params, $query, $translatable);
        }

        return $urls;
    }

    /**
     * @param array<mixed> $params
     * @param array<mixed> $query
     */
    private function urlFor(string $locale, ?string $route, array $params, array $query, mixed $translatable): string
    {
        if (null === $route || str_starts_with($route, '_')) {
            return $this->home($locale);
        }

        $params['_locale'] = $locale;
        if ($translatable instanceof Page && 'app_page_show' === $route) {
            // Page de l'arborescence : le chemin entier change avec la langue (un slug par niveau).
            $params['path'] = $this->pageTree->path($translatable, $locale);
        }
        if ($translatable instanceof TranslatableInterface && \array_key_exists('slug', $params)) {
            $translation = $translatable->translate($locale);
            if ($translation instanceof SluggableInterface) {
                $params['slug'] = $translation->getSlug();
            }
        }

        try {
            return $this->urlGenerator->generate($route, $query + $params);
        } catch (RoutingException) {
            return $this->home($locale);
        }
    }

    private function home(string $locale): string
    {
        return $this->urlGenerator->generate('app_home', ['_locale' => $locale]);
    }
}
