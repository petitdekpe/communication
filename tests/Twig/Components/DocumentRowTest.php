<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class DocumentRowTest extends ComponentTestCase
{
    public function testRendersDocumentWithAccessibleDownloadLink(): void
    {
        $crawler = $this->render('DocumentRow', [
            'title' => 'Rapport fictif',
            'href' => '/documents/rapport.pdf',
            'fileType' => 'pdf',
            'size' => 1258291,
            'date' => '2026-09-15',
        ]);

        self::assertSame('Rapport fictif', self::text($crawler->filter('p.font-semibold')));
        self::assertSame('PDF · 1,2 Mo · 15 sept. 2026', self::text($crawler->filter('p.text-small')));

        $link = $crawler->filter('a[download]');
        self::assertSame('/documents/rapport.pdf', $link->attr('href'));
        self::assertSame('Télécharger : Rapport fictif (PDF, 1,2 Mo)', self::text($link));
        self::assertCount(2, $crawler->filter('svg'), 'Icône du type + icône de téléchargement');
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function sizes(): iterable
    {
        yield '1 octet' => [1, '1 octet'];
        yield 'octets' => [812, '812 octets'];
        yield 'kilo-octets' => [48230, '47,1 Ko'];
        yield 'méga-octets' => [15728640, '15 Mo'];
        yield 'giga-octets' => [2411724800, '2,2 Go'];
    }

    #[DataProvider('sizes')]
    public function testHumanReadableSize(int $bytes, string $expected): void
    {
        $meta = self::text($this->render('DocumentRow', ['title' => 'Doc', 'href' => '/d', 'fileType' => 'other', 'size' => $bytes, 'date' => '2026-01-01'])->filter('p.text-small'));

        self::assertStringContainsString(' '.$expected.' ', $meta);
    }

    public function testRejectsUnknownFileType(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('DocumentRow', ['title' => 'Doc', 'href' => '/d', 'fileType' => 'odt', 'size' => 1, 'date' => '2026-01-01']);
    }
}
