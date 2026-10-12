<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Twig\Components\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class PaginationTest extends ComponentTestCase
{
    private const array ROUTE = ['route' => 'app_search', 'routeParams' => ['_locale' => 'fr', 'q' => 'presse']];

    /**
     * @return iterable<string, array{int, int, list<int|null>}>
     */
    public static function windows(): iterable
    {
        yield '1 sur 5' => [1, 5, [1, 2, null, 5]];
        yield '3 sur 5 : pas d\'ellipse pour un seul numéro' => [3, 5, [1, 2, 3, 4, 5]];
        yield '6 sur 12' => [6, 12, [1, null, 5, 6, 7, null, 12]];
        yield '12 sur 12' => [12, 12, [1, null, 11, 12]];
        yield '2 sur 2' => [2, 2, [1, 2]];
    }

    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('windows')]
    public function testPageWindow(int $current, int $total, array $expected): void
    {
        $component = $this->mountTwigComponent('Pagination', ['currentPage' => $current, 'totalPages' => $total] + self::ROUTE);
        self::assertInstanceOf(Pagination::class, $component);

        self::assertSame($expected, $component->getPages());
    }

    public function testRendersAccessibleLinks(): void
    {
        $crawler = $this->render('Pagination', ['currentPage' => 6, 'totalPages' => 12] + self::ROUTE);

        self::assertSame('Pagination', $crawler->filter('nav')->attr('aria-label'));

        $current = $crawler->filter('a[aria-current="page"]');
        self::assertSame('Page 6', self::text($current));
        self::assertSame('/fr/recherche?q=presse&page=6', $current->attr('href'));

        self::assertSame('Page Précédente', self::text($crawler->filter('a[rel="prev"]')));
        self::assertSame('Page Suivante', self::text($crawler->filter('a[rel="next"]')));
        self::assertSame('/fr/recherche?q=presse', $crawler->filter('a')->eq(1)->attr('href'), 'La page 1 n\'a pas de paramètre');
        self::assertCount(2, $crawler->filter('span[aria-hidden="true"]'), 'Deux ellipses');
    }

    public function testNoPreviousOnFirstPageNoNextOnLastPage(): void
    {
        self::assertCount(0, $this->render('Pagination', ['currentPage' => 1, 'totalPages' => 3] + self::ROUTE)->filter('a[rel="prev"]'));
        self::assertCount(0, $this->render('Pagination', ['currentPage' => 3, 'totalPages' => 3] + self::ROUTE)->filter('a[rel="next"]'));
    }

    public function testSinglePageRendersNothing(): void
    {
        self::assertSame('', trim($this->renderTwigComponent('Pagination', ['currentPage' => 1, 'totalPages' => 1] + self::ROUTE)->toString()));
    }

    public function testCurrentPageCannotExceedTotal(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('Pagination', ['currentPage' => 4, 'totalPages' => 3] + self::ROUTE);
    }
}
