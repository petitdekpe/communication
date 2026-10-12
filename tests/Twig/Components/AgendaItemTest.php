<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class AgendaItemTest extends ComponentTestCase
{
    public function testRendersDayMonthBlockTitleAndLocation(): void
    {
        $crawler = $this->render('AgendaItem', ['date' => '2026-10-14', 'title' => 'Événement fictif', 'location' => 'Lieu fictif', 'href' => '/fr/agenda/x']);

        $block = $crawler->filter('div[aria-hidden="true"] span');
        self::assertSame('14', self::text($block->eq(0)));
        self::assertSame('oct', self::text($block->eq(1)));

        self::assertSame('/fr/agenda/x', $crawler->filter('h3 a')->attr('href'));
        self::assertSame('mercredi 14 octobre 2026', self::text($crawler->filter('time')));
        self::assertSame('2026-10-14', $crawler->filter('time')->attr('datetime'));
        self::assertSame('Lieu : Lieu fictif', self::text($crawler->filter('p.text-small')));
    }

    public function testWithoutLinkNorLocation(): void
    {
        $crawler = $this->render('AgendaItem', ['date' => '2026-12-21', 'title' => 'Événement']);

        self::assertCount(0, $crawler->filter('a'));
        self::assertSame('Événement', self::text($crawler->filter('h3')));
        self::assertCount(0, $crawler->filter('p.text-small'));
    }

    public function testDateIsRequired(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('AgendaItem', ['title' => 'Événement']);
    }
}
