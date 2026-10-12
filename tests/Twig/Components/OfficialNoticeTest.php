<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class OfficialNoticeTest extends ComponentTestCase
{
    public function testDismissibleNoticeWithLink(): void
    {
        $crawler = $this->render('OfficialNotice', ['id' => 'avis-2026-10', 'message' => 'Message fictif.', 'href' => '/fr/avis']);

        $region = $crawler->filter('[role="region"]');
        self::assertSame('avis-avis-2026-10-titre', $region->attr('aria-labelledby'));
        self::assertSame('official-notice', $region->attr('data-controller'));
        self::assertSame('avis-2026-10', $region->attr('data-official-notice-id-value'));

        self::assertSame('Avis officiel', self::text($crawler->filter('strong#avis-avis-2026-10-titre')));
        self::assertSame('En savoir plus', self::text($crawler->filter('a[href="/fr/avis"]')));
        self::assertSame('Masquer cet avis', self::text($crawler->filter('button[data-action="official-notice#dismiss"]')));
    }

    public function testPermanentNoticeWithCustomTitle(): void
    {
        $crawler = $this->render('OfficialNotice', ['id' => 'permanent', 'title' => 'Information', 'message' => 'Message.', 'dismissible' => false]);

        self::assertNull($crawler->filter('[role="region"]')->attr('data-controller'));
        self::assertCount(0, $crawler->filter('button'));
        self::assertSame('Information', self::text($crawler->filter('strong')));
    }

    public function testIdMustBeAStorageFriendlySlug(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('OfficialNotice', ['id' => 'Avis du 6/10', 'message' => 'Message.']);
    }
}
