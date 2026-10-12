<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Article;
use App\Enum\ArticleType;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Factory\CategoryFactory;
use App\Factory\MediaFactory;
use App\Factory\OfficialDocumentFactory;
use App\Factory\TagFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Partie éditoriale : liste paginée, filtres en URL, communiqués, détail, 404 et flux RSS.
 * Seuls les contenus « published » dont publishedAt est passé sont visibles.
 */
final class NewsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    // ---- Liste, pagination ------------------------------------------------

    public function testListIsPaginatedByTwelveFromNewestToOldest(): void
    {
        $client = static::createClient();
        foreach (range(1, 14) as $day) {
            ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable(\sprintf('-%d days', $day))]);
        }

        $crawler = $client->request('GET', '/fr/actualites');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#news-results[data-turbo-action="advance"]');
        self::assertCount(12, $crawler->filter('turbo-frame article'));
        self::assertSame('14 articles', trim($crawler->filter('[role="status"]')->text()));
        $dates = $crawler->filter('turbo-frame article time')->each(static fn ($time): string => (string) $time->attr('datetime'));
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates, 'Ordre antéchronologique');

        $page2 = $client->request('GET', '/fr/actualites?page=2');
        self::assertCount(2, $page2->filter('turbo-frame article'));
        self::assertSelectorExists('a[aria-current="page"]');

        $client->request('GET', '/fr/actualites?page=3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyPublishedArticlesWithAPastPublicationDateAreListed(): void
    {
        $client = static::createClient();
        $visible = ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable('-1 day')]);
        ArticleFactory::new()->draft()->create();
        ArticleFactory::createOne(['status' => PublicationStatus::InReview, 'publishedAt' => new \DateTimeImmutable('-1 day')]);
        ArticleFactory::createOne(['status' => PublicationStatus::Archived, 'publishedAt' => new \DateTimeImmutable('-1 day')]);
        ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable('+2 days')]);

        $crawler = $client->request('GET', '/fr/actualites');

        self::assertCount(1, $crawler->filter('turbo-frame article'));
        self::assertSame((string) $visible->translate('fr')->getTitle(), trim($crawler->filter('turbo-frame article h2')->text()));
    }

    public function testEmptyListShowsAnEmptyState(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/actualites');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('turbo-frame h2', 'Aucun résultat');
        self::assertSelectorNotExists('turbo-frame nav[aria-label="Pagination"]');
    }

    public function testEnglishListUsesTranslatedPath(): void
    {
        $client = static::createClient();
        ArticleFactory::new()->frenchOnly()->create();

        $crawler = $client->request('GET', '/en/news');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('h1', 'News');
        self::assertSame('/fr/actualites', $crawler->filter('nav[aria-label="Language"] a[hreflang="fr"]')->attr('href'));
        self::assertCount(1, $crawler->filter('turbo-frame article[lang="fr"]'), 'Repli français signalé par lang');
    }

    // ---- Filtres ----------------------------------------------------------

    public function testFilterByTypeCategoryAndYearAreSharableUrls(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne();
        $slug = (string) $category->translate('fr')->getSlug();
        $target = ArticleFactory::new()->ofType(ArticleType::Dossier)->create(['category' => $category, 'publishedAt' => new \DateTimeImmutable('2025-06-15 10:00:00')]);
        ArticleFactory::new()->ofType(ArticleType::Dossier)->create(['publishedAt' => new \DateTimeImmutable('2025-06-15 10:00:00')]);
        ArticleFactory::new()->ofType(ArticleType::Communique)->create(['category' => $category, 'publishedAt' => new \DateTimeImmutable('2025-06-15 10:00:00')]);
        ArticleFactory::new()->ofType(ArticleType::Dossier)->create(['category' => $category, 'publishedAt' => new \DateTimeImmutable('2024-06-15 10:00:00')]);

        $count = static fn ($crawler): int => $crawler->filter('turbo-frame article')->count();

        self::assertSame(3, $count($client->request('GET', '/fr/actualites?type=dossier')));
        self::assertSame(3, $count($client->request('GET', '/fr/actualites?category='.$slug)));
        self::assertSame(3, $count($client->request('GET', '/fr/actualites?year=2025')));

        $crawler = $client->request('GET', '/fr/actualites?type=dossier&category='.$slug.'&year=2025');
        self::assertSame(1, $count($crawler));
        self::assertSame($target->translate('fr')->getTitle(), trim($crawler->filter('turbo-frame article h2')->text()));

        // Les filtres sont affichés comme sélectionnés et se conservent dans la pagination.
        self::assertSelectorExists('select[name="type"] option[value="dossier"][selected]');
        self::assertSelectorExists('select[name="year"] option[value="2025"][selected]');
        self::assertSelectorExists('a[href="/fr/actualites"].btn', 'Lien de réinitialisation');
    }

    public function testFilterValuesAreIgnoredOrEmptyWhenInvalid(): void
    {
        $client = static::createClient();
        ArticleFactory::createOne();

        self::assertCount(1, $client->request('GET', '/fr/actualites?type=nimporte&year=abc')->filter('turbo-frame article'));
        self::assertCount(0, $client->request('GET', '/fr/actualites?category=rubrique-inconnue')->filter('turbo-frame article'));
        self::assertResponseIsSuccessful();
    }

    public function testCategoryFilterAcceptsAFrenchSlugOnTheEnglishSite(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne();
        ArticleFactory::createOne(['category' => $category]);
        ArticleFactory::createOne();

        $crawler = $client->request('GET', '/en/news?category='.$category->translate('fr')->getSlug());

        self::assertCount(1, $crawler->filter('turbo-frame article'));
    }

    // ---- Communiqués ------------------------------------------------------

    public function testPressReleasesListOnlyReleasesNewestFirstWithPdfDownload(): void
    {
        $client = static::createClient();
        $older = ArticleFactory::new()->ofType(ArticleType::Communique)->create(['publishedAt' => new \DateTimeImmutable('-5 days')]);
        $newer = ArticleFactory::new()->ofType(ArticleType::Communique)->create([
            'publishedAt' => new \DateTimeImmutable('-1 day'),
            'attachments' => [OfficialDocumentFactory::createOne()],
        ]);
        ArticleFactory::new()->ofType(ArticleType::Actualite)->create();

        $crawler = $client->request('GET', '/fr/communiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Communiqués');
        $titles = $crawler->filter('turbo-frame li h2')->each(static fn ($h2): string => trim($h2->text()));
        self::assertSame([$newer->translate('fr')->getTitle(), $older->translate('fr')->getTitle()], $titles);

        $downloads = $crawler->filter('turbo-frame li a[download]');
        self::assertCount(1, $downloads, 'Téléchargement uniquement quand un PDF est joint');
        self::assertMatchesRegularExpression('#^/fr/medias/\d+/.+\.pdf$#', (string) $downloads->attr('href'));
        self::assertSelectorNotExists('select[name="type"]');

        $client->request('GET', '/en/press-releases');
        self::assertResponseIsSuccessful();
    }

    public function testUnpublishedPdfAttachmentIsNotOffered(): void
    {
        $client = static::createClient();
        ArticleFactory::new()->ofType(ArticleType::Communique)->create([
            'attachments' => [OfficialDocumentFactory::createOne(['status' => PublicationStatus::Draft, 'publishedAt' => null])],
        ]);

        self::assertCount(0, $client->request('GET', '/fr/communiques')->filter('a[download]'));
    }

    // ---- Détail -----------------------------------------------------------

    public function testDetailShowsEverythingThePageNeeds(): void
    {
        $client = static::createClient();
        $tag = TagFactory::createOne();
        $article = ArticleFactory::createOne([
            'coverImage' => MediaFactory::new()->image()->create(),
            'attachments' => [OfficialDocumentFactory::createOne()],
            'tags' => [$tag],
        ]);
        $related = ArticleFactory::createOne(['tags' => [$tag]]);
        ArticleFactory::createOne();
        $fr = $article->translate('fr');

        $crawler = $client->request('GET', '/fr/actualites/'.$fr->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', (string) $fr->getTitle());
        self::assertSelectorExists('article time[datetime]');
        self::assertSelectorExists('article .badge');
        self::assertSelectorExists('figure img[alt="[À VALIDER] Texte alternatif"]');
        self::assertSelectorTextContains('figcaption', '[À VALIDER] Crédit');
        self::assertSelectorExists('#pieces-jointes ~ div a[download]');
        self::assertCount(5, $crawler->filter('#partager ~ ul a'), 'Liens de partage');
        self::assertSelectorNotExists('script[src*="facebook"], script[src*="twitter"]');
        self::assertCount(1, $crawler->filter('section[aria-labelledby="articles-lies"] article'), 'Un seul article partage un mot-clé');
        self::assertSame($related->translate('fr')->getTitle(), trim($crawler->filter('section[aria-labelledby="articles-lies"] article h3')->text()));
    }

    public function testDetailGalleryIsRenderedWhenPresent(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::createOne();
        self::assertCount(0, $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug())->filter('#galerie'));
    }

    public function testBodyIsSanitized(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::createOne();
        $article->translate('fr')->setBody('<p>Texte <strong>fort</strong></p><script>alert(1)</script><h1>Gros</h1><a href="javascript:alert(2)" onclick="x()">piège</a><a href="https://exemple.test">lien</a><img src="x" onerror="y()">');
        $this->save($article);

        $html = (string) $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug())->filter('.prose-article')->html();

        self::assertStringContainsString('<strong>fort</strong>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('alert(1)', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('rel="noopener"', $html);
    }

    public function testStructuredDataIsEmbedded(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::createOne(['coverImage' => MediaFactory::new()->image()->create()]);

        $crawler = $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug());

        $documents = $crawler->filter('script[type="application/ld+json"]')->each(static fn ($script): array => json_decode($script->text('', false), true, flags: \JSON_THROW_ON_ERROR));
        $byType = array_column($documents, null, '@type');

        self::assertSame('Ministère de la Communication, chargé des Médias', $byType['GovernmentOrganization']['name']);
        $news = $byType['NewsArticle'];
        self::assertSame('https://schema.org', $news['@context']);
        self::assertSame($article->translate('fr')->getTitle(), $news['headline']);
        self::assertSame('fr', $news['inLanguage']);
        self::assertSame($article->getPublishedAt()?->format(\DATE_ATOM), $news['datePublished']);
        self::assertSame('GovernmentOrganization', $news['publisher']['@type']);
        self::assertStringStartsWith('http', $news['mainEntityOfPage']['@id']);
        self::assertStringContainsString('/fr/medias/', $news['image'][0]);
    }

    public function testUnavailableArticlesAreNotFound(): void
    {
        $client = static::createClient();
        $articles = [
            ArticleFactory::new()->draft()->create(),
            ArticleFactory::createOne(['status' => PublicationStatus::InReview]),
            ArticleFactory::createOne(['status' => PublicationStatus::Archived]),
            ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable('+1 hour')]),
        ];

        foreach ($articles as $article) {
            $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug());
            self::assertResponseStatusCodeSame(404);
        }
        $client->request('GET', '/fr/actualites/slug-inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    // ---- RSS --------------------------------------------------------------

    public function testRssFeedPerLanguage(): void
    {
        $client = static::createClient();
        $translated = ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable('-1 day')]);
        $frenchOnly = ArticleFactory::new()->frenchOnly()->create(['publishedAt' => new \DateTimeImmutable('-2 days')]);
        ArticleFactory::new()->draft()->create();
        ArticleFactory::createOne(['publishedAt' => new \DateTimeImmutable('+1 day')]);

        $client->request('GET', '/fr/actualites.rss');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/rss+xml', (string) $client->getResponse()->headers->get('Content-Type'));
        $xml = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($xml, 'XML bien formé');
        self::assertSame('fr', (string) $xml->channel->language);
        self::assertCount(2, $xml->channel->item, 'Brouillon et publication future exclus');
        self::assertSame($translated->translate('fr')->getTitle(), (string) $xml->channel->item[0]->title);
        self::assertStringEndsWith('/fr/actualites/'.$translated->translate('fr')->getSlug(), (string) $xml->channel->item[0]->link);
        self::assertStringStartsWith('http', (string) $xml->channel->item[0]->guid);
        self::assertSame($translated->getPublishedAt()?->format('r'), (string) $xml->channel->item[0]->pubDate);

        $client->request('GET', '/en/news.rss');
        $xml = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($xml);
        self::assertSame('en', (string) $xml->channel->language);
        self::assertSame($translated->translate('en')->getTitle(), (string) $xml->channel->item[0]->title);
        self::assertStringEndsWith('/en/news/'.$translated->translate('en')->getSlug(), (string) $xml->channel->item[0]->link);
        self::assertSame($frenchOnly->translate('fr')->getTitle(), (string) $xml->channel->item[1]->title, 'Repli français dans le flux anglais');
    }

    public function testListPagesAdvertiseTheFeed(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/fr/actualites');

        self::assertSame('/fr/actualites.rss', $crawler->filter('link[rel="alternate"][type="application/rss+xml"]')->attr('href'));
    }

    // ---- Fichiers ---------------------------------------------------------

    public function testMediaFilesAreServed(): void
    {
        $client = static::createClient();
        $media = MediaFactory::new()->pdf()->create();

        $client->request('GET', '/fr/medias/'.$media->getId().'/'.basename((string) $media->getPath()));

        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $client->request('GET', '/fr/medias/999999/fichier.pdf');
        self::assertResponseStatusCodeSame(404);
    }

    private function save(Article $article): void
    {
        static::getContainer()->get('doctrine')->getManager()->flush();
    }
}
