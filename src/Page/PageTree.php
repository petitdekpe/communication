<?php

declare(strict_types=1);

namespace App\Page;

use App\Entity\Contract\TranslatableInterface;
use App\Entity\Page;
use App\Repository\PageRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Arborescence des pages publiées : résolution d'un chemin (« le-ministere/missions ») vers une page, chemin et URL
 * d'une page dans une langue, ancêtres (fil d'Ariane) et sous-pages.
 *
 * Une page dont un ancêtre n'est pas publié n'est pas atteignable. La page d'accueil (clé système) est hors
 * arborescence : son URL est la racine de la langue. Le segment d'une langue sans traduction est celui du français,
 * comme pour les autres contenus.
 */
final class PageTree implements ResetInterface
{
    /** @var array<int, Page>|null pages atteignables, par identifiant */
    private ?array $pages = null;

    /** @var array<int, list<Page>> sous-pages, par identifiant du parent (0 : racine) */
    private array $children = [];

    private ?Page $home = null;

    public function __construct(
        private readonly PageRepository $repository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function reset(): void
    {
        $this->pages = null;
        $this->children = [];
        $this->home = null;
    }

    /** Page publiée située à ce chemin (segments séparés par « / »), ou null. */
    public function resolve(string $path, string $locale): ?Page
    {
        $this->load();
        $parent = 0;
        $page = null;
        foreach (explode('/', trim($path, '/')) as $segment) {
            $page = null;
            foreach ($this->children[$parent] ?? [] as $candidate) {
                if ($this->segment($candidate, $locale) === $segment || $this->segment($candidate, TranslatableInterface::DEFAULT_LOCALE) === $segment) {
                    $page = $candidate;
                    break;
                }
            }
            if (null === $page) {
                return null;
            }
            $parent = (int) $page->getId();
        }

        return $page;
    }

    /** Page d'accueil publiée, ou null. */
    public function home(): ?Page
    {
        $this->load();

        return $this->home;
    }

    /** Chemin de la page dans la langue (« le-ministere/missions ») ; vide pour l'accueil. */
    public function path(Page $page, string $locale): string
    {
        if ($page->isHome()) {
            return '';
        }

        $segments = array_map(fn (Page $p): string => $this->segment($p, $locale), [...$this->ancestors($page), $page]);

        return implode('/', $segments);
    }

    public function url(Page $page, string $locale, bool $absolute = false): string
    {
        $type = $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;
        if ($page->isHome()) {
            return $this->urlGenerator->generate('app_home', ['_locale' => $locale], $type);
        }

        return $this->urlGenerator->generate('app_page_show', ['_locale' => $locale, 'path' => $this->path($page, $locale)], $type);
    }

    /**
     * Ancêtres de la page, de la racine au parent direct.
     *
     * @return list<Page>
     */
    public function ancestors(Page $page): array
    {
        $ancestors = [];
        for ($parent = $page->getParent(); null !== $parent; $parent = $parent->getParent()) {
            array_unshift($ancestors, $parent);
            if (\count($ancestors) > 20) {
                break; // garde-fou contre un cycle en base
            }
        }

        return $ancestors;
    }

    /**
     * Fil d'Ariane : accueil, ancêtres puis page courante (sans lien).
     *
     * @return list<array{label: string, href?: string}>
     */
    public function breadcrumb(Page $page, string $locale, string $homeLabel): array
    {
        $items = [['label' => $homeLabel, 'href' => $this->urlGenerator->generate('app_home', ['_locale' => $locale])]];
        foreach ($this->ancestors($page) as $ancestor) {
            $items[] = ['label' => $this->title($ancestor, $locale), 'href' => $this->url($ancestor, $locale)];
        }
        $items[] = ['label' => $this->title($page, $locale)];

        return $items;
    }

    /**
     * Sous-pages publiées, dans l'ordre de leur position.
     *
     * @return list<Page>
     */
    public function children(?Page $parent): array
    {
        $this->load();

        return $this->children[null === $parent ? 0 : (int) $parent->getId()] ?? [];
    }

    /**
     * Pages publiées de l'arborescence, parents avant enfants (plan du site).
     *
     * @return list<array{page: Page, depth: int}>
     */
    public function flatten(): array
    {
        $this->load();
        $flat = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$flat): void {
            foreach ($this->children[$parent] ?? [] as $page) {
                $flat[] = ['page' => $page, 'depth' => $depth];
                $walk((int) $page->getId(), $depth + 1);
            }
        };
        $walk(0, 0);

        return $flat;
    }

    public function title(Page $page, string $locale): string
    {
        return (string) $page->translate($locale)->getTitle();
    }

    private function segment(Page $page, string $locale): string
    {
        return (string) $page->translate($locale)->getSlug();
    }

    private function load(): void
    {
        if (null !== $this->pages) {
            return;
        }

        $published = [];
        foreach ($this->repository->findAllPublished($this->clock->now()) as $page) {
            $published[(int) $page->getId()] = $page;
        }

        $this->pages = [];
        $this->children = [];
        foreach ($published as $id => $page) {
            if ($page->isHome()) {
                $this->home = $page;
                continue;
            }
            if (null !== $page->getSystemKey() || !$this->reachable($page, $published)) {
                continue;
            }
            $this->pages[$id] = $page;
            $this->children[(int) $page->getParent()?->getId()][] = $page;
        }
    }

    /**
     * @param array<int, Page> $published
     */
    private function reachable(Page $page, array $published): bool
    {
        for ($depth = 0, $parent = $page->getParent(); null !== $parent; $parent = $parent->getParent(), ++$depth) {
            if ($depth > 20 || !isset($published[(int) $parent->getId()]) || $parent->isHome()) {
                return false;
            }
        }

        return true;
    }
}
