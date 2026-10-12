<?php

declare(strict_types=1);

namespace App\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Invalidation par étiquettes des deux caches de pages : HTML complet des pages publiées (cache.page_html) et blocs
 * dynamiques (cache.page_blocks, 5 minutes). Appelée à chaque modification qui touche l'affichage public.
 */
final class ContentCache
{
    public function __construct(
        #[Autowire(service: 'cache.page_html')]
        private readonly TagAwareCacheInterface $pages,
        #[Autowire(service: 'cache.page_blocks')]
        private readonly TagAwareCacheInterface $blocks,
        #[Autowire(service: 'cache.menu')]
        private readonly CacheItemPoolInterface $menus,
    ) {
    }

    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags): void
    {
        if ([] === $tags) {
            return;
        }

        $this->pages->invalidateTags($tags);
        $this->blocks->invalidateTags($tags);
        // Les menus (cache.menu) contiennent des adresses de pages et les liens des paramètres du site.
        if ([] !== array_intersect($tags, [CacheTag::PAGES_TREE, CacheTag::SETTINGS, CacheTag::MENU])) {
            $this->menus->clear();
        }
    }

    /** Vide les deux caches (commande de maintenance, changement de gabarit). */
    public function clear(): void
    {
        $this->invalidate([CacheTag::CONTENT, CacheTag::PAGES_TREE, CacheTag::SETTINGS, CacheTag::MENU]);
    }
}
