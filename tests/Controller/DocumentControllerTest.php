<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\OfficialDocument;
use App\Enum\DocumentType;
use App\Enum\PublicationStatus;
use App\Factory\OfficialDocumentFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Bibliothèque /fr/documents : recherche FULLTEXT (titre + résumé), filtres, tri, formats selon la langue,
 * et téléchargement servi par le contrôleur depuis Flysystem avec compteur.
 */
final class DocumentControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    // ---- Recherche par mots-clés (index FULLTEXT) ---------------------------

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searches(): iterable
    {
        yield '1. mot du titre' => ['décret', ['A']];
        yield '2. sans accent (collation insensible aux accents)' => ['decret', ['A']];
        yield '3. mot du résumé uniquement' => ['agrément', ['B']];
        yield '4. préfixe d\'un mot' => ['agré', ['B']];
        yield '5. tous les mots sont obligatoires' => ['presse agrément', ['B']];
        yield '6. mots de documents différents : aucun résultat' => ['presse numérique', []];
        yield '7. mot absent' => ['zzzzzz', []];
        yield '8. opérateurs booléens saisis, neutralisés' => ['+décret -* "(~ @', ['A']];
        yield '9. insensible à la casse' => ['STRATÉGIE', ['D']];
        yield '10. plusieurs résultats, du plus récent au plus ancien' => ['nationale', ['D', 'C']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('searches')]
    public function testKeywordSearch(string $query, array $expected): void
    {
        $client = static::createClient();
        $documents = $this->library();

        $crawler = $client->request('GET', '/fr/documents', ['q' => $query]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            array_map(static fn (string $key): int => (int) $documents[$key]->getId(), $expected),
            $this->downloadedIds($crawler),
        );
        if ([] === $expected) {
            self::assertSelectorExists('turbo-frame#documents-results [role="status"]');
            self::assertStringContainsString('Aucun document', $crawler->filter('[role="status"]')->text());
            self::assertSelectorTextContains('main', 'Aucun résultat');
        }
    }

    public function testKeywordsTooShortToBeIndexedAreReportedNotMatchedEverywhere(): void
    {
        $client = static::createClient();
        $this->library();

        $crawler = $client->request('GET', '/fr/documents', ['q' => 'de la']);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->downloadedIds($crawler));
        self::assertStringContainsString('Les mots de moins de 3 lettres ne sont pas pris en compte', $crawler->filter('[role="status"]')->text());
    }

    public function testEnglishPageSearchesItsOwnLanguageThenFrench(): void
    {
        $client = static::createClient();
        $documents = $this->library();

        // Mot du résumé anglais de D (l'index InnoDB ignore les mots de plus de innodb_ft_max_token_size lettres : 84 par défaut, 10 sur le MariaDB WAMP local, d'où des mots courts dans ce jeu d'essai).
        $crawler = $client->request('GET', '/en/documents', ['q' => 'roadmap']);
        self::assertSame([(int) $documents['D']->getId()], $this->downloadedIds($crawler));
        self::assertStringContainsString('National digital strategy', $crawler->filter('turbo-frame ol')->text());

        // Mot français : le document est aussi trouvable depuis /en (repli sur le français).
        $crawler = $client->request('GET', '/en/documents', ['q' => 'décret']);
        self::assertSame([(int) $documents['A']->getId()], $this->downloadedIds($crawler));
    }

    public function testKeywordsAreCombinedWithOtherFilters(): void
    {
        $client = static::createClient();
        $documents = $this->library();

        $crawler = $client->request('GET', '/fr/documents', ['q' => 'nationale', 'type' => 'report']);
        self::assertSame([(int) $documents['C']->getId()], $this->downloadedIds($crawler));

        $crawler = $client->request('GET', '/fr/documents', ['q' => 'nationale', 'from' => '2026-01-01']);
        self::assertSame([(int) $documents['D']->getId()], $this->downloadedIds($crawler));
    }

    // ---- Type, période, tri ---------------------------------------------------

    public function testFilterByType(): void
    {
        $client = static::createClient();
        $documents = $this->library();

        $crawler = $client->request('GET', '/fr/documents', ['type' => 'decree']);

        self::assertSame([(int) $documents['A']->getId()], $this->downloadedIds($crawler));
        self::assertSame('1 document', trim($crawler->filter('[role="status"]')->text()));
        self::assertSelectorExists('select[name="type"] option[value="decree"][selected]');
        self::assertSelectorExists('a.btn-ghost', 'Lien de réinitialisation affiché quand un filtre est actif');
    }

    public function testFilterByPeriodIsInclusiveOnBothBounds(): void
    {
        $client = static::createClient();
        $documents = $this->library(); // A 2026-03-15, B 2025-06-30, C 2024-01-10, D 2026-09-01

        $crawler = $client->request('GET', '/fr/documents', ['from' => '2025-06-30', 'to' => '2026-03-15']);
        self::assertSame([(int) $documents['A']->getId(), (int) $documents['B']->getId()], $this->downloadedIds($crawler));

        $crawler = $client->request('GET', '/fr/documents', ['from' => '2026-03-16']);
        self::assertSame([(int) $documents['D']->getId()], $this->downloadedIds($crawler));

        $crawler = $client->request('GET', '/fr/documents', ['to' => '2024-12-31']);
        self::assertSame([(int) $documents['C']->getId()], $this->downloadedIds($crawler));

        // Bornes inversées : échangées plutôt que de ne rien renvoyer.
        $crawler = $client->request('GET', '/fr/documents', ['from' => '2026-03-15', 'to' => '2025-06-30']);
        self::assertSame([(int) $documents['A']->getId(), (int) $documents['B']->getId()], $this->downloadedIds($crawler));
    }

    public function testSortByDate(): void
    {
        $client = static::createClient();
        $documents = $this->library();
        $newestFirst = array_map(static fn (string $key): int => (int) $documents[$key]->getId(), ['D', 'A', 'B', 'C']);

        self::assertSame($newestFirst, $this->downloadedIds($client->request('GET', '/fr/documents')), 'Par défaut : du plus récent au plus ancien');
        self::assertSame(array_reverse($newestFirst), $this->downloadedIds($client->request('GET', '/fr/documents', ['sort' => 'date_asc'])));
        self::assertSame($newestFirst, $this->downloadedIds($client->request('GET', '/fr/documents', ['sort' => 'n-importe-quoi'])));
    }

    public function testInvalidParametersAreIgnored(): void
    {
        $client = static::createClient();
        $this->library();

        $crawler = $client->request('GET', '/fr/documents', ['type' => 'inconnu', 'from' => 'hier', 'to' => '2026-02-31', 'q' => ['a' => 'b'], 'page' => '-4']);

        self::assertResponseIsSuccessful();
        self::assertCount(4, $this->downloadedIds($crawler));
    }

    // ---- Publication, pagination -----------------------------------------------

    public function testOnlyPublishedDocumentsWithAFileAreListed(): void
    {
        $client = static::createClient();
        $visible = OfficialDocumentFactory::createOne();
        OfficialDocumentFactory::createOne(['status' => PublicationStatus::Draft]);
        OfficialDocumentFactory::createOne(['status' => PublicationStatus::Archived]);
        OfficialDocumentFactory::createOne(['publishedAt' => new \DateTimeImmutable('+2 days')]);
        OfficialDocumentFactory::createOne(['file' => null]);

        $crawler = $client->request('GET', '/fr/documents');

        self::assertSame([(int) $visible->getId()], $this->downloadedIds($crawler));
    }

    public function testListIsPaginatedByTen(): void
    {
        $client = static::createClient();
        foreach (range(1, 12) as $day) {
            OfficialDocumentFactory::createOne(['documentDate' => new \DateTimeImmutable(\sprintf('-%d days', $day))]);
        }

        $crawler = $client->request('GET', '/fr/documents');
        self::assertCount(10, $this->downloadedIds($crawler));
        self::assertSame('12 documents', trim($crawler->filter('[role="status"]')->text()));
        self::assertSelectorExists('a[aria-current="page"]');

        self::assertCount(2, $this->downloadedIds($client->request('GET', '/fr/documents', ['page' => 2])));

        $client->request('GET', '/fr/documents', ['page' => 3]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFiltersSurvivePagination(): void
    {
        $client = static::createClient();
        foreach (range(1, 11) as $day) {
            OfficialDocumentFactory::createOne(['type' => DocumentType::Law, 'documentDate' => new \DateTimeImmutable(\sprintf('-%d days', $day))]);
        }
        OfficialDocumentFactory::createOne(['type' => DocumentType::Form]);

        $crawler = $client->request('GET', '/fr/documents', ['type' => 'law']);

        self::assertSame('11 documents', trim($crawler->filter('[role="status"]')->text()));
        self::assertStringContainsString('type=law', (string) $crawler->filter('nav a[href*="page=2"]')->attr('href'));
    }

    // ---- Présentation : formats selon la langue --------------------------------

    public function testWeightDateAndLanguageAreFormattedForTheFrenchLocale(): void
    {
        $client = static::createClient();
        $document = $this->documentOfAboutOnePointTwoMegabytes();

        $crawler = $client->request('GET', '/fr/documents');

        $row = $this->normalize($crawler->filter('turbo-frame ol li')->text());
        self::assertStringContainsString('Décret · PDF · 1,2 Mo · Signé le 15 mars 2026', $row);
        self::assertStringContainsString('Réf. : [À VALIDER] · Langue du fichier : Anglais · 1 250 téléchargements', $row);
        self::assertSame('2026-03-15', $crawler->filter('turbo-frame time')->attr('datetime'));
        self::assertSame('/fr/documents/'.$document->getId().'/telechargement', $crawler->filter('a[download]')->attr('href'));
    }

    public function testWeightDateAndLanguageAreFormattedForTheEnglishLocale(): void
    {
        $client = static::createClient();
        $this->documentOfAboutOnePointTwoMegabytes();

        $crawler = $client->request('GET', '/en/documents');

        $row = $this->normalize($crawler->filter('turbo-frame ol li')->text());
        self::assertStringContainsString('Decree · PDF · 1.2 MB · Signed on Mar 15, 2026', $row);
        self::assertStringContainsString('Ref.: [À VALIDER] · File language: English · 1,250 downloads', $row);
        self::assertSelectorExists('a[href^="/en/documents/"][href$="/download"]');
    }

    public function testFormAndPageAreAccessible(): void
    {
        $client = static::createClient();
        $this->library();

        $crawler = $client->request('GET', '/fr/documents');

        self::assertSelectorExists('form[role="search"][aria-label]');
        foreach (['doc-q', 'doc-type', 'doc-sort', 'doc-from', 'doc-to'] as $id) {
            self::assertSelectorExists(\sprintf('label[for="%s"]', $id));
            self::assertSelectorExists('#'.$id);
        }
        self::assertCount(1, $crawler->filter('h1'));
    }

    // ---- Téléchargement ---------------------------------------------------------

    public function testDownloadServesTheFileFromTheStorageAndCountsIt(): void
    {
        $client = static::createClient();
        $document = OfficialDocumentFactory::createOne();
        $expected = (string) file_get_contents(\dirname(__DIR__, 2).'/fixtures/files/document-a-valider.pdf');

        $client->request('GET', '/fr/documents/'.$document->getId().'/telechargement');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=document-a-valider.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame((string) \strlen($expected), $response->headers->get('Content-Length'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame($expected, $client->getInternalResponse()->getContent());
        self::assertSame(1, $this->downloadCount($document));

        $client->request('GET', '/fr/documents/'.$document->getId().'/telechargement');
        $client->request('GET', '/en/documents/'.$document->getId().'/download');
        self::assertResponseIsSuccessful();
        self::assertSame(3, $this->downloadCount($document));
    }

    public function testHeadRequestsAreNotCounted(): void
    {
        $client = static::createClient();
        $document = OfficialDocumentFactory::createOne();

        $client->request('HEAD', '/fr/documents/'.$document->getId().'/telechargement');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->downloadCount($document));
    }

    public function testDownloadOfAnUnavailableDocumentIsNotFoundAndNotCounted(): void
    {
        $client = static::createClient();
        $unavailable = [
            OfficialDocumentFactory::createOne(['status' => PublicationStatus::Draft]),
            OfficialDocumentFactory::createOne(['publishedAt' => new \DateTimeImmutable('+2 days')]),
            OfficialDocumentFactory::createOne(['file' => null]),
        ];

        foreach ($unavailable as $document) {
            $client->request('GET', '/fr/documents/'.$document->getId().'/telechargement');
            self::assertResponseStatusCodeSame(404);
            self::assertSame(0, $this->downloadCount($document));
        }

        $client->request('GET', '/fr/documents/999999/telechargement');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/fr/documents/abc/telechargement');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDownloadWhoseFileIsMissingFromTheStorageIsNotFound(): void
    {
        $client = static::createClient();
        $document = OfficialDocumentFactory::createOne();
        $path = static::getContainer()->getParameter('kernel.project_dir').'/var/storage/media_test/'.$document->getFile()?->getPath();
        self::assertFileExists($path);
        unlink($path);

        $client->request('GET', '/fr/documents/'.$document->getId().'/telechargement');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->downloadCount($document));
    }

    // ---- Outils -----------------------------------------------------------------

    /**
     * Quatre documents aux dates, types et textes distincts.
     *
     * @return array<string, OfficialDocument>
     */
    private function library(): array
    {
        return [
            'A' => OfficialDocumentFactory::new()
                ->withTexts(['fr' => ['title' => 'Décret portant organisation des médias', 'summary' => 'Texte fondateur du secteur']])
                ->create(['type' => DocumentType::Decree, 'documentDate' => new \DateTimeImmutable('2026-03-15')]),
            'B' => OfficialDocumentFactory::new()
                ->withTexts(['fr' => ['title' => 'Arrêté relatif à la carte de presse', 'summary' => 'Procédure d\'agrément des reporters']])
                ->create(['type' => DocumentType::Order, 'documentDate' => new \DateTimeImmutable('2025-06-30')]),
            'C' => OfficialDocumentFactory::new()
                ->withTexts(['fr' => ['title' => 'Rapport annuel d\'activité', 'summary' => 'Bilan de la couverture nationale']])
                ->create(['type' => DocumentType::Report, 'documentDate' => new \DateTimeImmutable('2024-01-10')]),
            'D' => OfficialDocumentFactory::new()
                ->withTexts([
                    'fr' => ['title' => 'Stratégie du numérique', 'summary' => 'Feuille de route nationale'],
                    'en' => ['title' => 'National digital strategy', 'summary' => 'Radio reform roadmap'],
                ])
                ->create(['type' => DocumentType::Strategy, 'documentDate' => new \DateTimeImmutable('2026-09-01')]),
        ];
    }

    private function documentOfAboutOnePointTwoMegabytes(): OfficialDocument
    {
        $document = OfficialDocumentFactory::createOne([
            'type' => DocumentType::Decree,
            'documentDate' => new \DateTimeImmutable('2026-03-15'),
            'fileLanguage' => 'en',
        ]);
        $document->getFile()?->setSize(1258291);
        $this->setDownloadCount($document, 1250);

        return $document;
    }

    /**
     * @return list<int> identifiants des documents listés, dans l'ordre d'affichage
     */
    private function downloadedIds(Crawler $crawler): array
    {
        return array_map(
            static fn (string $href): int => (int) preg_replace('#^.*/documents/(\d+)/.*$#', '$1', $href),
            $crawler->filter('a[download]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
    }

    private function downloadCount(OfficialDocument $document): int
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();

        return $manager->getRepository(OfficialDocument::class)->find($document->getId())?->getDownloadCount() ?? -1;
    }

    private function setDownloadCount(OfficialDocument $document, int $count): void
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $manager->flush();
        $manager->createQuery('UPDATE '.OfficialDocument::class.' d SET d.downloadCount = :count WHERE d = :document')
            ->setParameter('count', $count)
            ->setParameter('document', $document)
            ->execute();
        $manager->clear();
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }
}
