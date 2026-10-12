<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class BreadcrumbTest extends ComponentTestCase
{
    public function testRendersLinksAndCurrentPage(): void
    {
        $crawler = $this->render('Breadcrumb', ['items' => [
            ['label' => 'Accueil', 'href' => '/fr'],
            ['label' => 'Actualités', 'href' => '/fr/actualites'],
            ['label' => 'Titre fictif'],
        ]]);

        self::assertSame('Fil d\'Ariane', $crawler->filter('nav')->attr('aria-label'));
        self::assertCount(3, $crawler->filter('ol > li'));
        self::assertSame(['/fr', '/fr/actualites'], $crawler->filter('a')->each(fn ($a): ?string => $a->attr('href')));
        self::assertSame('Titre fictif', self::text($crawler->filter('[aria-current="page"]')));
        self::assertCount(2, $crawler->filter('svg'), 'Un séparateur entre chaque niveau');
    }

    public function testAriaLabelCanBeOverridden(): void
    {
        $crawler = $this->render('Breadcrumb', ['aria-label' => 'Autre fil', 'items' => [['label' => 'Accueil']]]);

        self::assertSame('Autre fil', $crawler->filter('nav')->attr('aria-label'));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidItems(): iterable
    {
        yield 'vide' => [[]];
        yield 'niveau intermédiaire sans lien' => [[['label' => 'Accueil'], ['label' => 'Page']]];
        yield 'libellé manquant' => [[['href' => '/fr'], ['label' => 'Page']]];
    }

    /**
     * @param array<mixed> $items
     */
    #[DataProvider('invalidItems')]
    public function testRejectsInvalidItems(array $items): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('Breadcrumb', ['items' => $items]);
    }
}
