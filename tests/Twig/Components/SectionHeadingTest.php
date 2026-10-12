<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class SectionHeadingTest extends ComponentTestCase
{
    public function testRendersKickerTitleAndSeeAllLink(): void
    {
        $crawler = $this->render('SectionHeading', ['kicker' => 'Actualités', 'title' => 'Dernières nouvelles', 'href' => '/fr/actualites', 'id' => 'actus']);

        self::assertSame('Actualités', self::text($crawler->filter('p.type-caption')));
        $heading = $crawler->filter('h2#actus');
        self::assertSame('Dernières nouvelles', self::text($heading));

        $link = $crawler->filter('a');
        self::assertSame('/fr/actualites', $link->attr('href'));
        self::assertSame('Voir tout : Dernières nouvelles', self::text($link), 'Le titre complète le nom accessible du lien');
    }

    public function testCustomLinkLabelAndHeadingLevel(): void
    {
        $crawler = $this->render('SectionHeading', ['title' => 'Documents', 'href' => '/fr/documents', 'linkLabel' => 'Tous les documents', 'headingLevel' => 3]);

        self::assertCount(1, $crawler->filter('h3'));
        self::assertStringStartsWith('Tous les documents', self::text($crawler->filter('a')));
    }

    public function testWithoutKickerNorLink(): void
    {
        $crawler = $this->render('SectionHeading', ['title' => 'Titre seul']);

        self::assertCount(0, $crawler->filter('a'));
        self::assertCount(0, $crawler->filter('p'));
    }

    public function testRejectsInvalidHeadingLevel(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('SectionHeading', ['title' => 'Titre', 'headingLevel' => 7]);
    }
}
