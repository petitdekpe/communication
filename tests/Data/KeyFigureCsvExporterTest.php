<?php

declare(strict_types=1);

namespace App\Tests\Data;

use App\Data\KeyFigureCsvExporter;
use PHPUnit\Framework\TestCase;

/**
 * Neutralisation des formules : cas limites de l'export.
 */
final class KeyFigureCsvExporterTest extends TestCase
{
    public function testSafePrefixesFormulaCharactersOnly(): void
    {
        foreach (['=1+1', '+33', '-1', '@cmd', "\tx"] as $text) {
            self::assertStringStartsWith("'", KeyFigureCsvExporter::safe($text));
        }
        self::assertSame('Radios', KeyFigureCsvExporter::safe('Radios'));
        self::assertSame('', KeyFigureCsvExporter::safe(''));
    }

    public function testNumberDropsUselessDecimals(): void
    {
        self::assertSame('12.5', KeyFigureCsvExporter::number('12.5000'));
        self::assertSame('3', KeyFigureCsvExporter::number('3.0000'));
        self::assertSame('100', KeyFigureCsvExporter::number('100'));
    }
}
