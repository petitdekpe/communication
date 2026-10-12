<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class ArticleCardTest extends ComponentTestCase
{
    public function testRendersFullCard(): void
    {
        $crawler = $this->render('ArticleCard', [
            'title' => 'Titre fictif',
            'href' => '/fr/actualites/titre-fictif',
            'date' => '2026-09-28',
            'category' => 'communique',
            'excerpt' => 'Chapô fictif.',
            'image' => 'images/styleguide/exemple-16x9.svg',
            'imageAlt' => 'Description de l\'image',
        ]);

        $link = $crawler->filter('article h3 a');
        self::assertSame('/fr/actualites/titre-fictif', $link->attr('href'));
        self::assertSame('Titre fictif', self::text($link));
        self::assertStringContainsString('font-display', (string) $crawler->filter('h3')->attr('class'), 'Titre en Baskervville');

        $time = $crawler->filter('time');
        self::assertSame('2026-09-28', $time->attr('datetime'));
        self::assertSame('28 septembre 2026', self::text($time));

        self::assertSame('Communiqué', self::text($crawler->filter('.badge')));
        self::assertSame('Chapô fictif.', self::text($crawler->filter('p')));

        $image = $crawler->filter('img');
        self::assertSame('Description de l\'image', $image->attr('alt'));
        self::assertStringContainsString('/assets/images/styleguide/exemple-16x9', (string) $image->attr('src'));
    }

    public function testFeaturedCardHasLargerTitleAndEagerImage(): void
    {
        $crawler = $this->render('ArticleCard', ['title' => 'À la une', 'href' => '/fr/x', 'date' => '2026-10-05', 'excerpt' => 'Chapô.', 'image' => 'images/demo/photo-16x9.svg', 'imageAlt' => 'Photo', 'featured' => true]);

        self::assertStringContainsString('sm:text-h2', (string) $crawler->filter('h3')->attr('class'));
        self::assertStringContainsString('text-body', (string) $crawler->filter('p')->attr('class'));
        self::assertNull($crawler->filter('img')->attr('loading'), 'Image à la une chargée sans attendre');
        self::assertSame('lazy', $this->render('ArticleCard', ['title' => 'T', 'href' => '/fr/x', 'date' => '2026-10-05', 'image' => 'images/demo/photo-16x9.svg', 'imageAlt' => ''])->filter('img')->attr('loading'));
    }

    public function testMinimalCard(): void
    {
        $crawler = $this->render('ArticleCard', ['title' => 'Titre', 'href' => '/fr/x', 'date' => new \DateTimeImmutable('2026-10-01')]);

        self::assertCount(0, $crawler->filter('img'));
        self::assertCount(0, $crawler->filter('.badge'));
        self::assertCount(0, $crawler->filter('p'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidProps(): iterable
    {
        $base = ['title' => 'Titre', 'href' => '/fr/x', 'date' => '2026-10-01'];

        yield 'date manquante' => [['title' => 'Titre', 'href' => '/fr/x']];
        yield 'date invalide' => [['date' => 'pas une date'] + $base];
        yield 'image sans texte alternatif' => [['image' => 'images/x.svg'] + $base];
        yield 'catégorie inconnue' => [['category' => 'tribune'] + $base];
    }

    /**
     * @param array<string, mixed> $props
     */
    #[DataProvider('invalidProps')]
    public function testRejectsInvalidProps(array $props): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('ArticleCard', $props);
    }
}
