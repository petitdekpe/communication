<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class ServiceCardTest extends ComponentTestCase
{
    private const array PROPS = [
        'audience' => 'journalistes',
        'title' => 'Démarche fictive',
        'summary' => 'Résumé fictif.',
        'href' => '/fr/demarches/demarche-fictive',
    ];

    public function testRendersTitleAudienceAndSummaryOnly(): void
    {
        $crawler = $this->render('ServiceCard', self::PROPS + ['online' => true, 'cost' => '[À VALIDER]', 'delay' => '[À VALIDER]']);

        self::assertSame('/fr/demarches/demarche-fictive', $crawler->filter('h3 a')->attr('href'));
        self::assertSame('Public : Journalistes', self::text($crawler->filter('.badge')));
        self::assertSame('Résumé fictif.', self::text($crawler->filter('article > p')));
        self::assertCount(0, $crawler->filter('dl'), 'Coût, délai et « en ligne » ne figurent pas sur la carte');
    }

    public function testOnlineIsRequired(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('ServiceCard', self::PROPS);
    }
}
