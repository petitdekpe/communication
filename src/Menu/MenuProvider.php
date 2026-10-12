<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\Article;
use App\Entity\MenuItem as MenuItemEntity;
use App\Entity\MenuItemTranslation;
use App\Entity\Page;
use App\Entity\Service;
use App\Page\PageTree;
use App\Repository\MenuItemRepository;
use App\Settings\SiteSettings;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Source unique des liens de navigation (menu principal, barre utilitaire, pied de page, réseaux sociaux).
 *
 * Les menus principal (« main »), accès directs (« utility ») et pied de page (« footer ») sont ceux enregistrés dans
 * l'administration (éditeur de menus). Le résultat est mis en cache par menu et par langue ; le cache est vidé à chaque
 * enregistrement (invalidate()). Tant qu'un menu n'a aucune entrée en base, les définitions par défaut ci-dessous
 * s'appliquent (elles servent aussi à initialiser le menu depuis l'éditeur). Les réseaux sociaux viennent des
 * paramètres du site ; les liens institutionnels et légaux restent définis ici.
 *
 * Une entrée dont la cible n'existe pas (page non créée ou non publiée) n'est pas affichée : elle apparaît dès que la
 * page est publiée. Un groupe sans lien dont tous les enfants sont absents disparaît aussi.
 *
 * Format d'une définition par défaut :
 *  - lien interne : ['label' => clé de traduction, 'path' => ['fr' => slug, 'en' => slug], 'children' => [...]]
 *    (le slug est relatif au préfixe de langue : 'le-ministere' => /fr/le-ministere) ;
 *  - lien externe : ['label' => clé de traduction, 'url' => 'https://…', 'icon' => nom ux-icon] ;
 *  - groupe sans lien : ['label' => clé de traduction, 'children' => [...]].
 *
 * @phpstan-type LinkDefinition array{label: string, path?: array{fr: string, en: string}, url?: string, icon?: string}
 * @phpstan-type MenuDefinition array{label: string, path?: array{fr: string, en: string}, url?: string, icon?: string, children?: list<LinkDefinition>}
 * @phpstan-type CachedItem array{label: string, href: string|null, external: bool, children: list<mixed>}
 */
final class MenuProvider
{
    /** URL externe non encore communiquée : à remplacer avant mise en production. */
    public const string TO_VALIDATE = '#a-valider';

    public const string MAIN_MENU = 'main';
    public const string UTILITY_MENU = 'utility';
    public const string FOOTER_MENU = 'footer';

    /** Menus modifiables dans l'administration, avec leur profondeur maximale (1 = liste plate, 2 = un niveau de sous-menu). */
    public const array EDITABLE_MENUS = [self::MAIN_MENU => 2, self::UTILITY_MENU => 1, self::FOOTER_MENU => 2];

    private const array MAIN = [
        [
            'label' => 'menu.ministry.label',
            'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry'],
            'children' => [
                ['label' => 'menu.ministry.overview', 'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry']],
                ['label' => 'menu.ministry.missions', 'path' => ['fr' => 'le-ministere/missions', 'en' => 'the-ministry/missions']],
                ['label' => 'menu.ministry.organisation', 'path' => ['fr' => 'le-ministere/organisation', 'en' => 'the-ministry/organisation']],
                ['label' => 'menu.ministry.agencies', 'path' => ['fr' => 'le-ministere/structures-sous-tutelle', 'en' => 'the-ministry/supervised-bodies']],
            ],
        ],
        [
            'label' => 'menu.services.label',
            'path' => ['fr' => 'demarches', 'en' => 'services'],
            'children' => [
                ['label' => 'menu.services.all', 'path' => ['fr' => 'demarches', 'en' => 'services']],
                ['label' => 'menu.services.journalists', 'path' => ['fr' => 'demarches?public=journalistes', 'en' => 'services?public=journalistes']],
                ['label' => 'menu.services.media', 'path' => ['fr' => 'demarches?public=medias', 'en' => 'services?public=medias']],
                ['label' => 'menu.services.companies', 'path' => ['fr' => 'demarches?public=entreprises', 'en' => 'services?public=entreprises']],
                ['label' => 'menu.services.citizens', 'path' => ['fr' => 'demarches?public=citoyens', 'en' => 'services?public=citoyens']],
            ],
        ],
        [
            'label' => 'menu.news.label',
            'path' => ['fr' => 'actualites', 'en' => 'news'],
            'children' => [
                ['label' => 'menu.news.all', 'path' => ['fr' => 'actualites', 'en' => 'news']],
                ['label' => 'menu.news.press_releases', 'path' => ['fr' => 'communiques', 'en' => 'press-releases']],
                ['label' => 'menu.news.agenda', 'path' => ['fr' => 'agenda', 'en' => 'events']],
                ['label' => 'menu.news.consultations', 'path' => ['fr' => 'consultations', 'en' => 'consultations']],
            ],
        ],
        ['label' => 'menu.documents.label', 'path' => ['fr' => 'documents', 'en' => 'documents']],
        ['label' => 'menu.projects.label', 'path' => ['fr' => 'projets-et-reformes', 'en' => 'projects-and-reforms']],
        [
            'label' => 'menu.data.label',
            'path' => ['fr' => 'donnees', 'en' => 'data'],
            'children' => [
                ['label' => 'menu.data.key_figures', 'path' => ['fr' => 'donnees/chiffres-cles', 'en' => 'data/key-figures']],
                ['label' => 'menu.data.open_data', 'path' => ['fr' => 'donnees/donnees-ouvertes', 'en' => 'data/open-data']],
            ],
        ],
        ['label' => 'menu.contact.label', 'path' => ['fr' => 'contact', 'en' => 'contact']],
    ];

    private const array UTILITY = [
        ['label' => 'menu.utility.press', 'path' => ['fr' => 'espace-presse', 'en' => 'press-room']],
        ['label' => 'menu.utility.open_data', 'path' => ['fr' => 'donnees/donnees-ouvertes', 'en' => 'data/open-data']],
        ['label' => 'menu.utility.agenda', 'path' => ['fr' => 'agenda', 'en' => 'events']],
    ];

    private const array FOOTER_COLUMNS = [
        [
            'label' => 'menu.ministry.label',
            'children' => [
                ['label' => 'menu.ministry.overview', 'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry']],
                ['label' => 'menu.ministry.missions', 'path' => ['fr' => 'le-ministere/missions', 'en' => 'the-ministry/missions']],
                ['label' => 'menu.ministry.organisation', 'path' => ['fr' => 'le-ministere/organisation', 'en' => 'the-ministry/organisation']],
                ['label' => 'menu.projects.label', 'path' => ['fr' => 'projets-et-reformes', 'en' => 'projects-and-reforms']],
            ],
        ],
        [
            'label' => 'menu.services.label',
            'children' => [
                ['label' => 'menu.services.journalists', 'path' => ['fr' => 'demarches?public=journalistes', 'en' => 'services?public=journalistes']],
                ['label' => 'menu.services.media', 'path' => ['fr' => 'demarches?public=medias', 'en' => 'services?public=medias']],
                ['label' => 'menu.services.companies', 'path' => ['fr' => 'demarches?public=entreprises', 'en' => 'services?public=entreprises']],
                ['label' => 'menu.services.citizens', 'path' => ['fr' => 'demarches?public=citoyens', 'en' => 'services?public=citoyens']],
            ],
        ],
        [
            'label' => 'menu.footer.resources',
            'children' => [
                ['label' => 'menu.news.label', 'path' => ['fr' => 'actualites', 'en' => 'news']],
                ['label' => 'menu.news.press_releases', 'path' => ['fr' => 'communiques', 'en' => 'press-releases']],
                ['label' => 'menu.documents.label', 'path' => ['fr' => 'documents', 'en' => 'documents']],
                ['label' => 'menu.data.open_data', 'path' => ['fr' => 'donnees/donnees-ouvertes', 'en' => 'data/open-data']],
                ['label' => 'menu.utility.press', 'path' => ['fr' => 'espace-presse', 'en' => 'press-room']],
            ],
        ],
    ];

    /** Réseaux sociaux : adresse saisie dans les paramètres du site (marqueur [À VALIDER] tant qu'elle manque). */
    private const array SOCIAL = [
        ['label' => 'menu.social.facebook', 'setting' => SiteSettings::SOCIAL_FACEBOOK, 'icon' => 'ri:facebook-fill'],
        ['label' => 'menu.social.x', 'setting' => SiteSettings::SOCIAL_X, 'icon' => 'ri:twitter-x-line'],
        ['label' => 'menu.social.youtube', 'setting' => SiteSettings::SOCIAL_YOUTUBE, 'icon' => 'ri:youtube-fill'],
        ['label' => 'menu.social.linkedin', 'setting' => SiteSettings::SOCIAL_LINKEDIN, 'icon' => 'ri:linkedin-fill'],
    ];

    private const array INSTITUTIONAL = [
        ['label' => 'menu.institutional.gouv', 'url' => 'https://www.gouv.bj'],
        ['label' => 'menu.institutional.presidence', 'url' => 'https://presidence.bj'],
        ['label' => 'menu.institutional.service_public', 'url' => 'https://service-public.bj'],
    ];

    private const array LEGAL = [
        ['label' => 'menu.legal.notice', 'path' => ['fr' => 'mentions-legales', 'en' => 'legal-notice']],
        ['label' => 'menu.legal.accessibility', 'path' => ['fr' => 'accessibilite', 'en' => 'accessibility']],
        ['label' => 'menu.legal.sitemap', 'path' => ['fr' => 'plan-du-site', 'en' => 'sitemap']],
    ];

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly MenuItemRepository $items,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.menu')]
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RouterInterface $router,
        private readonly SiteSettings $settings,
        private readonly PageTree $pageTree,
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    /** @return list<MenuItem> */
    public function main(): array
    {
        return $this->editable(self::MAIN_MENU, self::MAIN);
    }

    /** @return list<MenuItem> */
    public function utility(): array
    {
        return $this->editable(self::UTILITY_MENU, self::UTILITY);
    }

    /** @return list<MenuItem> colonnes : chaque entrée est un groupe dont les enfants sont les liens */
    public function footerColumns(): array
    {
        return $this->editable(self::FOOTER_MENU, self::FOOTER_COLUMNS);
    }

    /** @return list<MenuItem> */
    public function social(): array
    {
        $locale = $this->locale();

        return array_map(function (array $network) use ($locale): MenuItem {
            $url = $this->settings->get($network['setting'], $locale);

            return new MenuItem(label: $network['label'], href: $url ?? self::TO_VALIDATE, external: null !== $url, icon: $network['icon']);
        }, self::SOCIAL);
    }

    /** @return list<MenuItem> */
    public function institutional(): array
    {
        return $this->build(self::INSTITUTIONAL);
    }

    /** @return list<MenuItem> */
    public function legal(): array
    {
        return $this->build(self::LEGAL);
    }

    /**
     * Définitions par défaut d'un menu modifiable (point de départ de l'éditeur).
     *
     * @return list<MenuDefinition>
     */
    public function defaults(string $menu): array
    {
        return match ($menu) {
            self::MAIN_MENU => self::MAIN,
            self::UTILITY_MENU => self::UTILITY,
            self::FOOTER_MENU => self::FOOTER_COLUMNS,
            default => throw new \InvalidArgumentException(\sprintf('Menu inconnu : %s.', $menu)),
        };
    }

    /** Vide le cache d'un menu (toutes langues) : à appeler après toute modification en base. */
    public function invalidate(string $menu): void
    {
        foreach ($this->locales as $locale) {
            $this->cache->delete($this->cacheKey($menu, $locale));
        }
    }

    /**
     * @param list<MenuDefinition|LinkDefinition> $defaults
     *
     * @return list<MenuItem>
     */
    private function editable(string $menu, array $defaults): array
    {
        $locale = $this->locale();
        try {
            /** @var list<CachedItem> $cached */
            $cached = $this->cache->get($this->cacheKey($menu, $locale), fn (): array => $this->resolve($menu, $locale));
        } catch (DbalException) {
            // Base indisponible : le site garde son menu par défaut plutôt que de tomber (rien n'est mis en cache).
            $cached = [];
        }

        return [] === $cached ? $this->build($defaults) : $this->hydrate($cached);
    }

    /**
     * Arbre de l'éditeur résolu pour une langue : libellés, URL finales, entrées sans cible valide écartées.
     *
     * @return list<CachedItem>
     */
    private function resolve(string $menu, string $locale): array
    {
        $byParent = [];
        foreach ($this->items->findForMenu($menu) as $item) {
            $byParent[$item->getParent()?->getId() ?? 0][] = $item;
        }

        return $this->resolveLevel($byParent, 0, $locale);
    }

    /**
     * @param array<int, list<MenuItemEntity>> $byParent
     *
     * @return list<CachedItem>
     */
    private function resolveLevel(array $byParent, int $parentId, string $locale): array
    {
        $resolved = [];
        foreach ($byParent[$parentId] ?? [] as $item) {
            $translation = $item->getTranslation($locale) ?? $item->getTranslation($this->defaultLocale);
            if (!$translation instanceof MenuItemTranslation || '' === trim((string) $translation->getLabel())) {
                continue;
            }

            $link = $this->link($item, $locale);
            $isGroup = null === $item->getTargetType() && '' === trim((string) $item->getExternalUrl()) && '' === trim((string) $translation->getPath());
            if (null === $link && !$isGroup) {
                continue; // cible supprimée, non publiée ou adresse invalide : l'entrée disparaît du site
            }

            $resolved[] = [
                'label' => (string) $translation->getLabel(),
                'href' => $link['href'] ?? null,
                'external' => $link['external'] ?? false,
                'children' => $this->resolveLevel($byParent, (int) $item->getId(), $locale),
            ];
        }

        return $resolved;
    }

    /**
     * @return array{href: string, external: bool}|null
     */
    private function link(MenuItemEntity $item, string $locale): ?array
    {
        $type = $item->getTargetType();
        if (null !== $type) {
            $href = $this->targetUrl($type, (int) $item->getTargetId(), $locale);

            return null === $href ? null : ['href' => $href, 'external' => false];
        }

        $url = trim((string) $item->getExternalUrl());
        if ('' !== $url) {
            return 1 === preg_match('~^https?://~i', $url) ? ['href' => $url, 'external' => true] : null;
        }

        $translation = $item->getTranslation($locale) ?? $item->getTranslation($this->defaultLocale);
        $path = $translation instanceof MenuItemTranslation ? trim((string) $translation->getPath()) : '';

        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || !$this->targetExists($path, $locale)) {
            return null;
        }

        return ['href' => $path, 'external' => false];
    }

    private function targetUrl(string $type, int $id, string $locale): ?string
    {
        $targets = [
            'page' => [null, Page::class],
            'article' => ['app_article_show', Article::class],
            'service' => ['app_service_show', Service::class],
        ];
        if (!isset($targets[$type])) {
            return null;
        }
        [$route, $class] = $targets[$type];

        $entity = $this->entityManager->find($class, $id);
        if (null === $entity || !$entity->isPublished($this->clock->now())) {
            return null;
        }

        if ($entity instanceof Page) {
            return $this->pageTree->url($entity, $locale);
        }

        try {
            return $this->urlGenerator->generate((string) $route, ['_locale' => $locale, 'slug' => $entity->translate($locale)->getSlug()]);
        } catch (RoutingException) {
            return null;
        }
    }

    /**
     * @param list<mixed> $cached
     *
     * @return list<MenuItem>
     */
    private function hydrate(array $cached): array
    {
        $items = [];
        foreach ($cached as $row) {
            /* @var CachedItem $row */
            $items[] = new MenuItem(label: $row['label'], href: $row['href'], external: $row['external'], children: $this->hydrate($row['children']));
        }

        return $items;
    }

    private function cacheKey(string $menu, string $locale): string
    {
        return \sprintf('menu.%s.%s', $menu, $locale);
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->defaultLocale;
    }

    /**
     * @param list<MenuDefinition|LinkDefinition> $definitions
     *
     * @return list<MenuItem>
     */
    private function build(array $definitions, ?string $locale = null): array
    {
        $locale ??= $this->locale();

        $items = [];
        foreach ($definitions as $definition) {
            $item = $this->buildItem($definition, $locale);
            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param MenuDefinition|LinkDefinition $definition
     */
    private function buildItem(array $definition, string $locale): ?MenuItem
    {
        $href = null;
        $external = false;
        $children = $this->build($definition['children'] ?? [], $locale);

        if (isset($definition['url'])) {
            $href = $definition['url'];
            $external = self::TO_VALIDATE !== $href;
        } elseif (isset($definition['path'])) {
            $slug = $definition['path'][$locale] ?? $definition['path'][$this->defaultLocale];
            $path = '/'.$locale.'/'.$slug;
            $href = $this->targetExists($path, $locale) ? $path : null;
        }

        if (null === $href && [] === $children) {
            return null; // page inexistante ou non publiée, ou groupe vide : pas de lien mort
        }

        return new MenuItem(
            label: $definition['label'],
            href: $href,
            external: $external,
            icon: $definition['icon'] ?? null,
            children: $children,
        );
    }

    /** Vrai si le chemin (avec ou sans paramètres de requête) mène à une route ou à une page publiée. */
    private function targetExists(string $path, string $locale): bool
    {
        try {
            $match = $this->router->match(explode('?', explode('#', $path)[0])[0]);
        } catch (RoutingException) {
            return false;
        }

        if ('app_page_show' !== ($match['_route'] ?? null)) {
            return true;
        }

        return null !== $this->pageTree->resolve((string) ($match['path'] ?? ''), (string) ($match['_locale'] ?? $locale));
    }
}
