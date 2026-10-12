<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class StatCardTest extends ComponentTestCase
{
    public function testRendersFormattedValueWithSourceAndDate(): void
    {
        $crawler = $this->render('StatCard', ['value' => 1250, 'label' => 'Libellé fictif', 'source' => 'Source fictive', 'sourceUrl' => 'https://exemple.test', 'updatedAt' => '2026-09-30']);

        self::assertSame('1 250', self::text($crawler->filter('p.font-display')), 'Séparateur de milliers français');
        self::assertSame('Libellé fictif', self::text($crawler->filter('p.font-semibold')));
        self::assertSame('https://exemple.test', $crawler->filter('figcaption a')->attr('href'));
        self::assertSame('Source : Source fictive Mise à jour : 30 septembre 2026', self::text($crawler->filter('figcaption')));
    }

    public function testDecimalWithUnitAndTextualValue(): void
    {
        self::assertSame('72,5 %', self::text($this->render('StatCard', ['value' => 72.5, 'unit' => '%', 'label' => 'L', 'source' => 'S', 'updatedAt' => '2026-01-01'])->filter('p.font-display')));
        $textual = $this->render('StatCard', ['value' => '[À VALIDER]', 'label' => 'L', 'source' => 'S', 'updatedAt' => '2026-01-01'])->filter('p.font-display');
        self::assertSame('[À VALIDER]', self::text($textual));
        self::assertStringContainsString('text-h2', (string) $textual->attr('class'), 'Valeur textuelle plus petite qu\'un nombre');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unsourcedStats(): iterable
    {
        yield 'sans source' => [['value' => 10, 'label' => 'L', 'updatedAt' => '2026-01-01']];
        yield 'source vide' => [['value' => 10, 'label' => 'L', 'source' => '', 'updatedAt' => '2026-01-01']];
        yield 'sans date de mise à jour' => [['value' => 10, 'label' => 'L', 'source' => 'S']];
    }

    /**
     * @param array<string, mixed> $props
     */
    #[DataProvider('unsourcedStats')]
    public function testSourceAndDateAreMandatory(array $props): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('StatCard', $props);
    }
}
