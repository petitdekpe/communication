<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class HeroTest extends ComponentTestCase
{
    public function testRendersFullHero(): void
    {
        $crawler = $this->render('Hero', [
            'kicker' => 'Surtitre',
            'title' => 'Titre fictif',
            'lead' => 'Chapô fictif.',
            'image' => 'images/styleguide/exemple-4x3.svg',
            'imageAlt' => '',
            'primaryLabel' => 'Action 1',
            'primaryHref' => '/fr/a',
            'secondaryLabel' => 'Action 2',
            'secondaryHref' => '/fr/b',
        ]);

        $heading = $crawler->filter('h1');
        self::assertSame('Titre fictif', self::text($heading));
        self::assertStringContainsString('type-display', (string) $heading->attr('class'));
        self::assertSame('Surtitre', self::text($crawler->filter('p.type-caption')));

        $buttons = $crawler->filter('a.btn');
        self::assertCount(2, $buttons);
        self::assertStringContainsString('btn-primary', (string) $buttons->eq(0)->attr('class'));
        self::assertStringContainsString('btn-outline', (string) $buttons->eq(1)->attr('class'));
        self::assertCount(1, $buttons->eq(0)->filter('svg'), 'Icône du bouton principal rendue en SVG');

        self::assertSame('', $crawler->filter('img')->attr('alt'));
    }

    public function testContentBlockIsRenderedInTheTextColumn(): void
    {
        $rendered = $this->renderTwigComponent('Hero', ['title' => 'Titre'], '<form role="search"><input name="q"></form>');

        self::assertCount(1, $rendered->crawler()->filter('h1 ~ form[role="search"] input[name="q"]'));
    }

    public function testMinimalHeroWithHeadingLevel(): void
    {
        $crawler = $this->render('Hero', ['title' => 'Titre', 'headingLevel' => 2]);

        self::assertCount(1, $crawler->filter('h2'));
        self::assertCount(0, $crawler->filter('a, img'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unpairedProps(): iterable
    {
        yield 'bouton sans lien' => [['title' => 'T', 'primaryLabel' => 'Action']];
        yield 'lien sans libellé' => [['title' => 'T', 'secondaryHref' => '/fr']];
        yield 'image sans texte alternatif' => [['title' => 'T', 'image' => 'images/x.svg']];
    }

    /**
     * @param array<string, mixed> $props
     */
    #[DataProvider('unpairedProps')]
    public function testRejectsUnpairedProps(array $props): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('Hero', $props);
    }
}
