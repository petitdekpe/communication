<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class EmptyStateTest extends ComponentTestCase
{
    public function testRendersMessageAndAction(): void
    {
        $crawler = $this->render('EmptyState', ['title' => 'Aucun résultat', 'message' => 'Message fictif.', 'actionLabel' => 'Réinitialiser', 'actionHref' => '/fr/actualites']);

        self::assertSame('Aucun résultat', self::text($crawler->filter('h2')));
        self::assertSame('Message fictif.', self::text($crawler->filter('p')));
        $action = $crawler->filter('a.btn.btn-outline');
        self::assertSame('/fr/actualites', $action->attr('href'));
        self::assertSame('Réinitialiser', self::text($action));
        self::assertCount(1, $crawler->filter('svg'));
    }

    public function testMinimalWithCustomIconAndLevel(): void
    {
        $crawler = $this->render('EmptyState', ['title' => 'Liste vide', 'icon' => 'ri:file-text-line', 'headingLevel' => 3]);

        self::assertCount(1, $crawler->filter('h3'));
        self::assertCount(0, $crawler->filter('a'));
        self::assertCount(0, $crawler->filter('p'));
    }

    public function testActionNeedsLabelAndHref(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('EmptyState', ['title' => 'Vide', 'actionLabel' => 'Agir']);
    }
}
