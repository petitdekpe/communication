<?php

declare(strict_types=1);

namespace App\Tests\Document;

use App\Document\FulltextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FulltextQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function inputs(): iterable
    {
        yield 'un mot' => ['décret', '+décret*'];
        yield 'plusieurs mots, tous obligatoires' => ['accréditation presse', '+accréditation* +presse*'];
        yield 'espaces superflus' => ["  loi   média \t", '+loi* +média*'];
        yield 'opérateurs booléens neutralisés' => ['+décret -loi ~presse <média >radio (arrêté) "texte" *', '+décret* +loi* +presse* +média* +radio* +arrêté* +texte*'];
        yield 'mots trop courts ignorés' => ['la loi de la presse', '+loi* +presse*'];
        yield 'uniquement des mots trop courts' => ['de la', null];
        yield 'ponctuation seule' => ['+-*()"@<>~', null];
        yield 'vide' => ['', null];
        yield 'chiffres' => ['2026', '+2026*'];
        yield 'injection de syntaxe' => ['loi@5 OR 1=1; --', '+loi*'];
    }

    #[DataProvider('inputs')]
    public function testBooleanMode(string $input, ?string $expected): void
    {
        self::assertSame($expected, FulltextQuery::booleanMode($input));
    }

    public function testNumberOfTermsIsCapped(): void
    {
        $query = FulltextQuery::booleanMode(implode(' ', array_fill(0, 30, 'presse')));

        self::assertNotNull($query);
        self::assertCount(FulltextQuery::MAX_TERMS, explode(' ', $query));
    }
}
