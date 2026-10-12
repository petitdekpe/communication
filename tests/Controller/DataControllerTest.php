<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\KeyFigureTranslation;
use App\Enum\PublicationStatus;
use App\Factory\KeyFigureFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Page des chiffres clés (source et date obligatoires) et export CSV.
 */
final class DataControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', 'donnees'];
        yield 'en' => ['en', 'data'];
    }

    #[DataProvider('locales')]
    public function testListShowsEveryPublishedFigureWithItsSourceAndDate(string $locale, string $path): void
    {
        $client = static::createClient();
        KeyFigureFactory::createOne(['value' => '1250.5000', 'position' => 1]);
        KeyFigureFactory::createOne(['value' => '42.0000', 'position' => 2, 'sourceUrl' => 'https://example.org/source']);
        KeyFigureFactory::new()->create(['status' => PublicationStatus::Draft]);

        $crawler = $client->request('GET', \sprintf('/%s/%s', $locale, $path));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, 'tbody tr', 'Les brouillons ne sont pas publics');
        self::assertSelectorExists('table caption');
        self::assertSelectorCount(4, 'thead th[scope="col"]');
        self::assertSelectorCount(2, 'tbody th[scope="row"]');
        self::assertSelectorCount(2, 'tbody time[datetime]', 'Chaque chiffre a sa date de mise à jour');
        self::assertStringContainsString('example.org/source', (string) $crawler->filter('tbody a')->attr('href'));
        self::assertSelectorTextContains('tbody tr:first-child', 'fr' === $locale ? '250,5' : '1,250.5');
        self::assertSelectorExists(\sprintf('a[href="/%s/%s/export.csv"]', $locale, $path));
    }

    public function testCsvExportContainsSourceAndDateAndIsSafeForSpreadsheets(): void
    {
        $client = static::createClient();
        $figure = KeyFigureFactory::createOne(['value' => '12.5000', 'sourceUrl' => 'https://example.org/s', 'position' => 1]);
        $figure->translate('fr')->setLabel('=HYPERLINK("http://evil")')->setUnit('%');
        $second = KeyFigureFactory::createOne(['value' => null, 'position' => 2]);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $client->request('GET', '/fr/donnees/export.csv');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=chiffres-cles-fr.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
        $csv = $client->getInternalResponse()->getContent();

        self::assertStringStartsWith("\xEF\xBB\xBF".'label;value;unit;source;source_url;data_date;language', $csv);
        $lines = explode("\r\n", trim($csv));
        self::assertCount(3, $lines);
        self::assertStringStartsWith("'=HYPERLINK", $this->csvRow($lines[1])[0], 'Formule neutralisée');
        self::assertSame('12.5', $this->csvRow($lines[1])[1]);
        self::assertSame('%', $this->csvRow($lines[1])[2]);
        self::assertSame('https://example.org/s', $this->csvRow($lines[1])[4]);
        self::assertSame($figure->getDataDate()?->format('Y-m-d'), $this->csvRow($lines[1])[5]);
        self::assertSame('', $this->csvRow($lines[2])[1], 'Valeur non validée : cellule vide');
        self::assertNotSame('', $this->csvRow($lines[2])[3], 'La source est toujours renseignée');
        unset($second);
    }

    public function testEnglishExportUsesEnglishLabels(): void
    {
        $client = static::createClient();
        $figure = KeyFigureFactory::createOne(['value' => '3.0000']);

        $client->request('GET', '/en/data/export.csv');
        $csv = $client->getInternalResponse()->getContent();

        self::assertStringContainsString((string) $figure->translate('en')->getLabel(), $csv);
        self::assertStringContainsString(';3;', $csv);
        self::assertStringContainsString(';en', $csv);
    }

    public function testKeyFigureTranslationRequiresASource(): void
    {
        $violations = self::getContainer()->get('validator')->validate(new KeyFigureTranslation());

        $fields = array_map(static fn ($v): string => $v->getPropertyPath(), iterator_to_array($violations));
        self::assertContains('source', $fields);
        self::assertContains('label', $fields);
    }

    /**
     * @return list<string>
     */
    private function csvRow(string $line): array
    {
        return str_getcsv($line, ';', '"', '\\');
    }
}
