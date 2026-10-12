<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Pagination : précédent / suivant, première et dernière page, pages voisines, ellipses.
 * Les URL sont générées sur la route donnée ; la page 1 n'a pas de paramètre (URL canonique).
 *
 *   <twig:Pagination :currentPage="3" :totalPages="12" route="app_news" :routeParams="{category: 'communiques'}" />
 */
#[AsTwigComponent]
final class Pagination
{
    public int $currentPage;
    public int $totalPages;
    public string $route;
    /** @var array<string, mixed> */
    public array $routeParams = [];
    public string $pageParameter = 'page';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        foreach (['currentPage', 'totalPages'] as $option) {
            $resolver->setRequired($option)->setAllowedTypes($option, 'int')->setAllowedValues($option, static fn (int $page): bool => $page >= 1);
        }
        Props::requiredString($resolver, 'route', 'pageParameter');
        $resolver->setDefault('pageParameter', 'page');
        $resolver->setDefault('routeParams', [])->setAllowedTypes('routeParams', 'array');

        $resolved = Props::resolve($resolver, $data);
        if ($resolved['currentPage'] > $resolved['totalPages']) {
            throw new InvalidOptionsException('"currentPage" ne peut pas dépasser "totalPages".');
        }

        return $resolved;
    }

    /**
     * Pages à afficher ; null = ellipse. Ex. page 6 sur 12 : 1 … 5 6 7 … 12.
     *
     * @return list<int|null>
     */
    public function getPages(): array
    {
        $visible = array_unique([1, $this->currentPage - 1, $this->currentPage, $this->currentPage + 1, $this->totalPages]);
        $visible = array_values(array_filter($visible, fn (int $page): bool => $page >= 1 && $page <= $this->totalPages));
        sort($visible);

        $pages = [];
        $previous = 0;
        foreach ($visible as $page) {
            if (2 === $page - $previous) {
                $pages[] = $previous + 1; // un seul numéro manquant : on l'affiche plutôt qu'une ellipse
            } elseif ($page - $previous > 2) {
                $pages[] = null;
            }
            $pages[] = $page;
            $previous = $page;
        }

        return $pages;
    }

    public function url(int $page): string
    {
        $params = $this->routeParams;
        unset($params[$this->pageParameter]);
        if ($page > 1) {
            $params[$this->pageParameter] = $page;
        }

        return $this->urlGenerator->generate($this->route, $params);
    }
}
