<?php

declare(strict_types=1);

namespace App\Menu;

use App\Page\PageTree;
use App\Settings\SiteSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Source unique des liens de navigation (menu principal, barre utilitaire, pied de page, réseaux sociaux).
 *
 * Les menus sont définis dans cette classe, ils ne sont pas modifiables dans l'administration. Les réseaux sociaux
 * viennent des paramètres du site ; les liens institutionnels et légaux restent définis ici.
 *
 * Une entrée dont la cible n'existe pas (page non créée ou non publiée) n'est pas affichée : elle apparaît dès que la
 * page est publiée. Un groupe sans lien dont tous les enfants sont absents disparaît aussi.
 *
 * Format d'une définition par défaut :
 *  - lien interne : ['label' => clé de traduction, 'path' => ['fr' => slug, 'en' => slug], 'children' => [...]]
 *    (le slug est relatif au préfixe de langue : 'le-ministere' => /fr/le-ministere) ;
 *  - lien externe : ['label' => clé de traduction, 'url' => 'https://…', 'icon' => nom ux-icon] ;
 *  - groupe sans lien : ['label' => clé de traduction, 'children' => [...]] ;
 *  - 'feature' => 'agenda' : l'entrée n'apparaît que si l'agenda est activé dans les paramètres du site.
 *
 * @phpstan-type LinkDefinition array{label: string, path?: array{fr: string, en: string}, url?: string, icon?: string, feature?: string}
 * @phpstan-type MenuDefinition array{label: string, path?: array{fr: string, en: string}, url?: string, icon?: string, feature?: string, children?: list<LinkDefinition>}
 */
final class MenuProvider
{
    /** URL externe non encore communiquée : à remplacer avant mise en production. */
    public const string TO_VALIDATE = '#a-valider';

    private const array MAIN = [
        [
            'label' => 'menu.ministry.label',
            'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry'],
            'children' => [
                ['label' => 'menu.ministry.overview', 'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry']],
                ['label' => 'menu.ministry.minister', 'path' => ['fr' => 'la-ministre', 'en' => 'the-minister']],
                ['label' => 'menu.ministry.missions', 'path' => ['fr' => 'le-ministere/missions', 'en' => 'the-ministry/missions']],
                ['label' => 'menu.ministry.organisation', 'path' => ['fr' => 'le-ministere/organisation', 'en' => 'the-ministry/organisation']],
                ['label' => 'menu.ministry.agencies', 'path' => ['fr' => 'le-ministere/structures-sous-tutelle', 'en' => 'the-ministry/supervised-bodies']],
            ],
        ],
        [
            'label' => 'menu.action.label',
            'path' => ['fr' => 'nos-actions', 'en' => 'our-actions'],
            'children' => [
                ['label' => 'menu.action.actions', 'path' => ['fr' => 'nos-actions', 'en' => 'our-actions']],
                ['label' => 'menu.action.fields', 'path' => ['fr' => 'domaines-d-intervention', 'en' => 'fields-of-intervention']],
                ['label' => 'menu.projects.label', 'path' => ['fr' => 'programmes-et-projets', 'en' => 'programmes-and-projects']],
            ],
        ],
        [
            'label' => 'menu.news.label',
            'path' => ['fr' => 'actualites', 'en' => 'news'],
            'children' => [
                ['label' => 'menu.news.all', 'path' => ['fr' => 'actualites', 'en' => 'news']],
                ['label' => 'menu.news.press_releases', 'path' => ['fr' => 'communiques', 'en' => 'press-releases']],
                ['label' => 'menu.news.agenda', 'path' => ['fr' => 'agenda', 'en' => 'events'], 'feature' => 'agenda'],
                ['label' => 'menu.news.consultations', 'path' => ['fr' => 'consultations', 'en' => 'consultations']],
            ],
        ],
        [
            'label' => 'menu.press.label',
            'path' => ['fr' => 'espace-medias', 'en' => 'media-room'],
            'children' => [
                ['label' => 'menu.press.label', 'path' => ['fr' => 'espace-medias', 'en' => 'media-room']],
                ['label' => 'menu.press.accreditation', 'path' => ['fr' => 'demarches?public=journalistes', 'en' => 'services?public=journalistes']],
                ['label' => 'menu.media_library.label', 'path' => ['fr' => 'mediatheque', 'en' => 'media-library']],
            ],
        ],
        [
            'label' => 'menu.resources.label',
            'path' => ['fr' => 'documents', 'en' => 'documents'],
            'children' => [
                ['label' => 'menu.documents.label', 'path' => ['fr' => 'documents', 'en' => 'documents']],
                ['label' => 'menu.understand.label', 'path' => ['fr' => 'comprendre-les-medias', 'en' => 'understanding-the-media']],
                ['label' => 'menu.professionals.label', 'path' => ['fr' => 'espace-professionnels', 'en' => 'professionals']],
                ['label' => 'menu.services.label', 'path' => ['fr' => 'demarches', 'en' => 'services']],
                ['label' => 'menu.data.key_figures', 'path' => ['fr' => 'donnees', 'en' => 'data']],
            ],
        ],
        ['label' => 'menu.contact.label', 'path' => ['fr' => 'contact', 'en' => 'contact']],
    ];

    private const array UTILITY = [
        ['label' => 'menu.utility.press', 'path' => ['fr' => 'espace-medias', 'en' => 'media-room']],
        ['label' => 'menu.utility.open_data', 'path' => ['fr' => 'donnees/donnees-ouvertes', 'en' => 'data/open-data']],
        ['label' => 'menu.utility.agenda', 'path' => ['fr' => 'agenda', 'en' => 'events'], 'feature' => 'agenda'],
    ];

    private const array FOOTER_COLUMNS = [
        [
            'label' => 'menu.ministry.label',
            'children' => [
                ['label' => 'menu.ministry.overview', 'path' => ['fr' => 'le-ministere', 'en' => 'the-ministry']],
                ['label' => 'menu.ministry.minister', 'path' => ['fr' => 'la-ministre', 'en' => 'the-minister']],
                ['label' => 'menu.action.actions', 'path' => ['fr' => 'nos-actions', 'en' => 'our-actions']],
                ['label' => 'menu.action.fields', 'path' => ['fr' => 'domaines-d-intervention', 'en' => 'fields-of-intervention']],
                ['label' => 'menu.projects.label', 'path' => ['fr' => 'programmes-et-projets', 'en' => 'programmes-and-projects']],
            ],
        ],
        [
            'label' => 'menu.footer.media',
            'children' => [
                ['label' => 'menu.news.label', 'path' => ['fr' => 'actualites', 'en' => 'news']],
                ['label' => 'menu.press.label', 'path' => ['fr' => 'espace-medias', 'en' => 'media-room']],
                ['label' => 'menu.documents.label', 'path' => ['fr' => 'documents', 'en' => 'documents']],
                ['label' => 'menu.understand.label', 'path' => ['fr' => 'comprendre-les-medias', 'en' => 'understanding-the-media']],
                ['label' => 'menu.professionals.label', 'path' => ['fr' => 'espace-professionnels', 'en' => 'professionals']],
                ['label' => 'menu.media_library.label', 'path' => ['fr' => 'mediatheque', 'en' => 'media-library']],
            ],
        ],
        [
            'label' => 'menu.footer.services',
            'children' => [
                ['label' => 'menu.services.journalists', 'path' => ['fr' => 'demarches?public=journalistes', 'en' => 'services?public=journalistes']],
                ['label' => 'menu.services.media', 'path' => ['fr' => 'demarches?public=medias', 'en' => 'services?public=medias']],
                ['label' => 'menu.news.consultations', 'path' => ['fr' => 'consultations', 'en' => 'consultations']],
                ['label' => 'menu.contact.label', 'path' => ['fr' => 'contact', 'en' => 'contact']],
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
        ['label' => 'menu.institutional.sgg', 'url' => self::TO_VALIDATE],
        ['label' => 'menu.institutional.bodies', 'path' => ['fr' => 'le-ministere/structures-sous-tutelle', 'en' => 'the-ministry/supervised-bodies']],
    ];

    private const array LEGAL = [
        ['label' => 'menu.legal.notice', 'path' => ['fr' => 'mentions-legales', 'en' => 'legal-notice']],
        ['label' => 'menu.legal.privacy', 'path' => ['fr' => 'politique-de-confidentialite', 'en' => 'privacy-policy']],
        ['label' => 'menu.legal.cookies', 'path' => ['fr' => 'politique-cookies', 'en' => 'cookie-policy']],
        ['label' => 'menu.legal.accessibility', 'path' => ['fr' => 'accessibilite', 'en' => 'accessibility']],
        ['label' => 'menu.legal.sitemap', 'path' => ['fr' => 'plan-du-site', 'en' => 'sitemap']],
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly SiteSettings $settings,
        private readonly PageTree $pageTree,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    /** @return list<MenuItem> */
    public function main(): array
    {
        return $this->build(self::MAIN);
    }

    /** @return list<MenuItem> */
    public function utility(): array
    {
        return $this->build(self::UTILITY);
    }

    /** @return list<MenuItem> colonnes : chaque entrée est un groupe dont les enfants sont les liens */
    public function footerColumns(): array
    {
        return $this->build(self::FOOTER_COLUMNS);
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
        if ('agenda' === ($definition['feature'] ?? null) && !$this->settings->agendaEnabled()) {
            return null;
        }

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
