<?php

declare(strict_types=1);

namespace App\Tests\Version;

use App\Version\TextDiffer;
use PHPUnit\Framework\TestCase;

final class TextDifferTest extends TestCase
{
    public function testIdenticalTextsHaveNoChange(): void
    {
        self::assertSame([['type' => 'same', 'text' => 'Bonjour le monde']], (new TextDiffer())->diff('Bonjour le monde', 'Bonjour le monde'));
    }

    public function testReplacedWordIsMarkedAsDeletedAndInserted(): void
    {
        $ops = (new TextDiffer())->diff('Le ministre annonce une mesure', 'Le ministre précise une mesure');

        self::assertSame([
            ['type' => 'same', 'text' => 'Le ministre '],
            ['type' => 'del', 'text' => 'annonce'],
            ['type' => 'ins', 'text' => 'précise'],
            ['type' => 'same', 'text' => ' une mesure'],
        ], $ops);
    }

    public function testBothSidesCanBeRebuiltFromTheOperations(): void
    {
        $old = '<p>Premier paragraphe</p> <p>Deuxième <strong>version</strong></p>';
        $new = '<p>Premier paragraphe modifié</p> <p>Deuxième version</p> <p>Ajout</p>';
        $ops = (new TextDiffer())->diff($old, $new);

        $left = implode('', array_map(static fn (array $op): string => 'ins' === $op['type'] ? '' : $op['text'], $ops));
        $right = implode('', array_map(static fn (array $op): string => 'del' === $op['type'] ? '' : $op['text'], $ops));
        self::assertSame($old, $left);
        self::assertSame($new, $right);
    }

    public function testEmptySides(): void
    {
        self::assertSame([['type' => 'ins', 'text' => 'Nouveau']], (new TextDiffer())->diff('', 'Nouveau'));
        self::assertSame([['type' => 'del', 'text' => 'Ancien']], (new TextDiffer())->diff('Ancien', ''));
        self::assertSame([], (new TextDiffer())->diff('', ''));
    }
}
