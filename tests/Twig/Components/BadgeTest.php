<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Enum\Audience;
use App\Enum\ContentCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class BadgeTest extends ComponentTestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function values(): iterable
    {
        yield 'actualité' => ['actualite', 'Actualité', 'bg-primary-light'];
        yield 'communiqué' => ['communique', 'Communiqué', 'bg-primary'];
        yield 'dossier' => ['dossier', 'Dossier', 'bg-cream'];
        yield 'annonce' => ['annonce', 'Annonce', 'bg-flag-green'];
        yield 'avis officiel' => ['avis-officiel', 'Avis officiel', 'bg-flag-red'];
        yield 'journalistes' => ['journalistes', 'Public : Journalistes', 'ring-primary'];
        yield 'entreprises' => ['entreprises', 'Public : Entreprises', 'ring-primary'];
        yield 'citoyens' => ['citoyens', 'Public : Citoyens', 'ring-primary'];
    }

    #[DataProvider('values')]
    public function testRendersLabelAndStyle(string $value, string $expectedText, string $expectedClass): void
    {
        $badge = $this->render('Badge', ['value' => $value])->filter('span.badge');

        self::assertSame($expectedText, self::text($badge));
        self::assertStringContainsString($expectedClass, (string) $badge->attr('class'));
    }

    public function testAcceptsEnumInstances(): void
    {
        self::assertSame('Dossier', self::text($this->render('Badge', ['value' => ContentCategory::Dossier])));
        self::assertSame('Public : Citoyens', self::text($this->render('Badge', ['value' => Audience::Citoyens])));
    }

    public function testRejectsUnknownValue(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('Badge', ['value' => 'editorial']);
    }
}
