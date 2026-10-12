<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\BodySectionsExtension;
use PHPUnit\Framework\TestCase;

final class BodySectionsExtensionTest extends TestCase
{
    public function testSplitSeparatesIntroAndSections(): void
    {
        $result = (new BodySectionsExtension())->split('<p>Intro</p><h2>A</h2><p>a</p><h2>B</h2><p>b</p>');

        self::assertSame('<p>Intro</p>', $result['intro']);
        self::assertSame(['<h2>A</h2><p>a</p>', '<h2>B</h2><p>b</p>'], $result['sections']);
    }

    public function testDefinitionCardsStripLeadingNumbers(): void
    {
        $cards = (new BodySectionsExtension())->definitionCards('<h2>T</h2><dl><dt>Action 1 : Structurer</dt><dd>Un.</dd><dt>2. Médias</dt><dd>Deux.</dd></dl>');

        self::assertNotNull($cards);
        self::assertSame('<h2>T</h2>', $cards['before']);
        self::assertSame(['Structurer', 'Médias'], array_column($cards['items'], 'title'));
        self::assertTrue($cards['numbered']);
    }

    public function testDefinitionCardsAreNullWithoutList(): void
    {
        self::assertNull((new BodySectionsExtension())->definitionCards('<h2>T</h2><p>x</p>'));
    }

    public function testHeadingCardsKeepProjectTitlesAndListItems(): void
    {
        $cards = (new BodySectionsExtension())->headingCards('<h2>T</h2><p>i</p><h3>Projet 1 : Registre</h3><dl><dt>Objectif</dt><dd>a</dd></dl><h3>Projet 2 : Radios</h3><dl><dt>Objectif</dt><dd>b</dd></dl>');

        self::assertNotNull($cards);
        self::assertSame('<h2>T</h2><p>i</p>', $cards['before']);
        self::assertSame(['Projet 1 : Registre', 'Projet 2 : Radios'], array_column($cards['cards'], 'title'));
        self::assertSame(['Objectif'], array_column($cards['cards'][0]['items'], 'title'));
    }

    public function testHeadingCardsAreNullWithoutSubheadings(): void
    {
        self::assertNull((new BodySectionsExtension())->headingCards('<h2>T</h2><dl><dt>a</dt><dd>b</dd></dl>'));
    }

    public function testDefinitionCardsAreNotNumberedWithoutNumbers(): void
    {
        $cards = (new BodySectionsExtension())->definitionCards('<dl><dt><a href="/x">Communiqués</a></dt><dd>a</dd></dl>');

        self::assertNotNull($cards);
        self::assertFalse($cards['numbered']);
    }
}
