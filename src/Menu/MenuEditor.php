<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\Article;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\MenuItem;
use App\Entity\MenuItemTranslation;
use App\Entity\Page;
use App\Entity\Service;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Opérations de l'éditeur de menus : arbre, création, modification, suppression, réordonnancement, initialisation
 * depuis les valeurs par défaut, recherche d'un contenu à lier. Toute écriture vide le cache du menu concerné
 * (MenuProvider::invalidate) : la modification apparaît sur le site sans déploiement.
 */
final class MenuEditor
{
    /** Types de contenu pouvant être liés, avec leur classe d'entité. */
    private const array TARGETS = ['page' => Page::class, 'article' => Article::class, 'service' => Service::class];

    private const int SEARCH_LIMIT = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuItemRepository $items,
        private readonly MenuProvider $provider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<MenuNode>
     */
    public function tree(string $menu, string $locale): array
    {
        $byParent = [];
        foreach ($this->items->findForMenu($menu) as $item) {
            $byParent[$item->getParent()?->getId() ?? 0][] = $item;
        }

        return $this->nodes($byParent, 0, $locale);
    }

    public function isEmpty(string $menu): bool
    {
        return [] === $this->items->findBy(['menu' => $menu], limit: 1);
    }

    public function create(string $menu, MenuItemData $data): MenuItem
    {
        $item = (new MenuItem())->setMenu($menu);
        $item->setPosition($this->nextPosition($menu, $data->parent));
        $this->entityManager->persist($item);
        $this->apply($item, $data);
        $this->entityManager->flush();
        $this->provider->invalidate($menu);

        return $item;
    }

    public function update(MenuItem $item, MenuItemData $data): void
    {
        if ($data->parent?->getId() !== $item->getParent()?->getId()) {
            $item->setPosition($this->nextPosition((string) $item->getMenu(), $data->parent));
        }
        $this->apply($item, $data);
        $this->entityManager->flush();
        $this->provider->invalidate((string) $item->getMenu());
    }

    public function delete(MenuItem $item): void
    {
        $menu = (string) $item->getMenu();
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->provider->invalidate($menu);
    }

    public function dataFor(MenuItem $item): MenuItemData
    {
        $data = new MenuItemData();
        $data->labelFr = $this->translation($item, 'fr')?->getLabel();
        $data->labelEn = $this->translation($item, 'en')?->getLabel();
        $data->parent = $item->getParent();

        if (null !== $item->getTargetType()) {
            $data->linkType = MenuItemData::LINK_CONTENT;
            $data->target = $item->getTargetType().':'.$item->getTargetId();
        } elseif (null !== $item->getExternalUrl() && '' !== $item->getExternalUrl()) {
            $data->linkType = MenuItemData::LINK_URL;
            $data->url = $item->getExternalUrl();
        } elseif ('' !== trim((string) $this->translation($item, 'fr')?->getPath())) {
            $data->linkType = MenuItemData::LINK_PATH;
            $data->pathFr = $this->translation($item, 'fr')?->getPath();
            $data->pathEn = $this->translation($item, 'en')?->getPath();
        }

        return $data;
    }

    /**
     * Applique l'arbre reçu de l'éditeur (identifiants, enfants imbriqués). Refusé sans rien modifier si l'arbre ne contient
     * pas exactement les entrées du menu ou dépasse la profondeur autorisée.
     *
     * @param list<mixed> $tree
     */
    public function reorder(string $menu, array $tree): bool
    {
        $maxDepth = MenuProvider::EDITABLE_MENUS[$menu] ?? 0;
        $known = [];
        foreach ($this->items->findForMenu($menu) as $item) {
            $known[(int) $item->getId()] = $item;
        }

        $placements = [];
        if (!$this->flatten($tree, null, 1, $maxDepth, $placements) || \count($placements) !== \count($known)) {
            return false;
        }
        foreach ($placements as $id => $placement) {
            if (!isset($known[$id]) || (null !== $placement['parent'] && !isset($known[$placement['parent']]))) {
                return false;
            }
        }

        foreach ($placements as $id => $placement) {
            $known[$id]->setParent(null === $placement['parent'] ? null : $known[$placement['parent']]);
            $known[$id]->setPosition($placement['position']);
        }
        $this->entityManager->flush();
        $this->provider->invalidate($menu);

        return true;
    }

    /**
     * Crée le menu à partir des valeurs par défaut (libellés français et anglais, chemins par langue).
     * Sans effet si le menu contient déjà des entrées.
     */
    public function importDefaults(string $menu): int
    {
        if (!$this->isEmpty($menu)) {
            return 0;
        }

        $count = $this->importLevel($menu, $this->provider->defaults($menu), null);
        $this->entityManager->flush();
        $this->provider->invalidate($menu);

        return $count;
    }

    /**
     * Pages, actualités et démarches dont le titre contient la recherche (dans l'une ou l'autre langue).
     *
     * @return list<array{type: string, id: int, label: string, detail: string}>
     */
    public function search(string $query, string $locale): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $found = [];
        foreach (self::TARGETS as $type => $class) {
            /** @var list<object> $entities */
            $entities = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($class, 'e')
                ->innerJoin('e.translations', 't')
                ->where('t.title LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%')
                ->groupBy('e.id')
                ->setMaxResults(self::SEARCH_LIMIT)
                ->getQuery()
                ->getResult();

            foreach ($entities as $entity) {
                if ($entity instanceof Page || $entity instanceof Article || $entity instanceof Service) {
                    $found[] = [
                        'type' => $type,
                        'id' => (int) $entity->getId(),
                        'label' => $this->title($entity, $locale),
                        'detail' => $this->translator->trans('admin.menus.type.'.$type),
                    ];
                }
            }
        }

        return $found;
    }

    /** Libellé du contenu désigné (« page:12 »), pour réafficher le choix dans le formulaire. */
    public function targetLabel(?string $target, string $locale): ?string
    {
        if (null === $target || 1 !== preg_match('/^(page|article|service):(\d+)$/', $target, $m)) {
            return null;
        }
        $entity = $this->entityManager->find(self::TARGETS[$m[1]], (int) $m[2]);

        return $entity instanceof TranslatableInterface ? $this->title($entity, $locale) : null;
    }

    /**
     * @param array<int, list<MenuItem>> $byParent
     *
     * @return list<MenuNode>
     */
    private function nodes(array $byParent, int $parentId, string $locale): array
    {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $item) {
            $translation = $item->getTranslation($locale) ?? $item->getTranslation('fr');
            $nodes[] = new MenuNode(
                $item,
                $translation instanceof MenuItemTranslation ? (string) $translation->getLabel() : '',
                $this->destination($item, $translation instanceof MenuItemTranslation ? $translation : null, $locale),
                $this->nodes($byParent, (int) $item->getId(), $locale),
            );
        }

        return $nodes;
    }

    private function destination(MenuItem $item, ?MenuItemTranslation $translation, string $locale): string
    {
        if (null !== $item->getTargetType()) {
            return $this->targetLabel($item->getTargetType().':'.$item->getTargetId(), $locale) ?? $this->translator->trans('admin.menus.target_missing');
        }
        if (null !== $item->getExternalUrl() && '' !== $item->getExternalUrl()) {
            return $item->getExternalUrl();
        }

        return trim((string) $translation?->getPath());
    }

    private function apply(MenuItem $item, MenuItemData $data): void
    {
        $item->setParent($data->parent);
        $item->setExternalUrl(null);
        $target = $data->targetParts();
        $item->setTarget($target[0] ?? null, $target[1] ?? null);

        $paths = ['fr' => null, 'en' => null];
        if (MenuItemData::LINK_URL === $data->linkType) {
            $item->setExternalUrl(trim((string) $data->url));
        } elseif (MenuItemData::LINK_PATH === $data->linkType) {
            $paths = ['fr' => trim((string) $data->pathFr), 'en' => trim((string) $data->pathEn)];
        }

        $labelFr = trim((string) $data->labelFr);
        $labels = ['fr' => $labelFr, 'en' => '' === trim((string) $data->labelEn) ? '' : trim((string) $data->labelEn)];
        foreach ($labels as $locale => $label) {
            $translation = $this->translation($item, $locale);
            if (!$translation instanceof MenuItemTranslation) {
                if ('' === $label) {
                    continue; // pas de version anglaise : le menu affiche le libellé français
                }
                $translation = (new MenuItemTranslation())->setLocale($locale);
                $item->addTranslation($translation);
            }
            if ('' === $label) {
                $item->removeTranslation($translation);
                continue;
            }
            $translation->setLabel($label);
            $translation->setPath('' === (string) $paths[$locale] ? null : $paths[$locale]);
        }
    }

    private function translation(MenuItem $item, string $locale): ?MenuItemTranslation
    {
        $translation = $item->getTranslation($locale);

        return $translation instanceof MenuItemTranslation ? $translation : null;
    }

    private function nextPosition(string $menu, ?MenuItem $parent): int
    {
        $max = -1;
        foreach ($this->items->findForMenu($menu) as $sibling) {
            if ($sibling->getParent()?->getId() === $parent?->getId()) {
                $max = max($max, $sibling->getPosition());
            }
        }

        return $max + 1;
    }

    /**
     * @param list<mixed>                                        $level
     * @param array<int, array{parent: int|null, position: int}> $placements
     */
    private function flatten(array $level, ?int $parentId, int $depth, int $maxDepth, array &$placements): bool
    {
        // Une liste vide (les enfants d'une entrée sans enfant) est toujours valide, quelle que soit la profondeur.
        if ([] !== $level && $depth > $maxDepth) {
            return false;
        }
        foreach ($level as $position => $node) {
            if (!\is_array($node) || !isset($node['id']) || !\is_int($node['id']) || isset($placements[$node['id']])) {
                return false;
            }
            $placements[$node['id']] = ['parent' => $parentId, 'position' => $position];
            $children = $node['children'] ?? [];
            if (!\is_array($children) || !$this->flatten(array_values($children), $node['id'], $depth + 1, $maxDepth, $placements)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private function importLevel(string $menu, array $definitions, ?MenuItem $parent): int
    {
        $count = 0;
        foreach ($definitions as $position => $definition) {
            $item = (new MenuItem())->setMenu($menu)->setParent($parent)->setPosition($position);
            if (isset($definition['url']) && \is_string($definition['url'])) {
                $item->setExternalUrl($definition['url']);
            }
            foreach (['fr', 'en'] as $locale) {
                $translation = (new MenuItemTranslation())->setLocale($locale);
                $translation->setLabel($this->translator->trans((string) $definition['label'], [], 'messages', $locale));
                $slugs = $definition['path'] ?? null;
                if (\is_array($slugs) && isset($slugs[$locale])) {
                    $translation->setPath('/'.$locale.'/'.$slugs[$locale]);
                }
                $item->addTranslation($translation);
            }
            $this->entityManager->persist($item);
            ++$count;
            if (isset($definition['children']) && \is_array($definition['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = $definition['children'];
                $count += $this->importLevel($menu, $children, $item);
            }
        }

        return $count;
    }

    private function title(TranslatableInterface $entity, string $locale): string
    {
        $translation = $entity->translate($locale);

        return method_exists($translation, 'getTitle') ? (string) $translation->getTitle() : '';
    }
}
