<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ArticleTranslation;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Factory\EventFactory;
use App\Factory\MediaGalleryFactory;
use App\Factory\OfficialDocumentFactory;
use App\Factory\PageFactory;
use App\Factory\ServiceFactory;
use App\Factory\VideoFactory;
use App\Tests\EnablesAgenda;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Recherche globale : articles, documents, démarches, événements, pages ; résultats groupés par type.
 */
final class SearchControllerTest extends WebTestCase
{
    use EnablesAgenda;
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', 'recherche'];
        yield 'en' => ['en', 'search'];
    }

    #[DataProvider('locales')]
    public function testResultsAreGroupedByType(string $locale, string $path): void
    {
        $client = static::createClient();
        $this->enableAgenda();
        $article = ArticleFactory::createOne();
        $this->retitle($article->translate('fr'), 'Zorglub article');
        $this->retitle($article->translate('en'), 'Zorglub article');
        foreach ([
            OfficialDocumentFactory::createOne(),
            ServiceFactory::createOne(),
            EventFactory::createOne(),
            PageFactory::createOne(),
            MediaGalleryFactory::createOne(),
            VideoFactory::createOne(),
        ] as $content) {
            foreach (['fr', 'en'] as $l) {
                $content->translate($l)->setTitle('Zorglub '.$content::class);
            }
        }
        ArticleFactory::new()->draft()->create();
        self::getContainer()->get('doctrine')->getManager()->flush();

        $crawler = $client->request('GET', \sprintf('/%s/%s?q=zorglub', $locale, $path));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(7, 'section[data-search-group]');
        $order = $crawler->filter('section[data-search-group]')->each(static fn ($s): string => (string) $s->attr('data-search-group'));
        self::assertSame(['article', 'document', 'service', 'event', 'page', 'gallery', 'video'], $order, 'Groupes dans l\'ordre des types');
        self::assertSelectorCount(1, 'section[data-search-group="article"] li');
        self::assertSelectorExists(\sprintf('section[data-search-group="service"] a[href^="/%s/"]', $locale));
        self::assertSelectorExists('nav[aria-label] a[href="#resultats-page"]');
    }

    public function testHeaderSearchBarSubmitsToTheSearchPage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr');

        self::assertSelectorExists('header form[role="search"][action="/fr/recherche"] input[type="search"][name="q"]');
        self::assertSelectorExists('header form[role="search"] label[for="entete-recherche-q"]');
    }

    public function testEveryResultLinkOpens(): void
    {
        $client = static::createClient();
        $this->enableAgenda();
        $article = ArticleFactory::createOne();
        $document = OfficialDocumentFactory::createOne();
        $service = ServiceFactory::createOne();
        $event = EventFactory::createOne();
        $page = PageFactory::createOne();
        $gallery = MediaGalleryFactory::createOne();
        $video = VideoFactory::createOne();
        foreach ([$article, $document, $service, $event, $page, $gallery, $video] as $content) {
            $content->translate('fr')->setTitle('Zorglub '.$content::class);
        }
        self::getContainer()->get('doctrine')->getManager()->flush();

        $crawler = $client->request('GET', '/fr/recherche?q=zorglub');
        $links = $crawler->filter('section[data-search-group] a[href]')->each(static fn ($a): string => (string) $a->attr('href'));
        self::assertCount(7, $links);

        foreach ($links as $link) {
            $client->request('GET', $link);
            self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Lien de résultat cassé : '.$link);
        }
    }

    public function testContentOnlyInFrenchIsFoundOnTheEnglishSiteWithItsLanguageDeclared(): void
    {
        $client = static::createClient();
        $page = PageFactory::new()->frenchOnly()->create();
        $page->translate('fr')->setTitle('Bonjour Zorglub');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $client->request('GET', '/en/search?q=zorglub');

        self::assertSelectorExists('section[data-search-group="page"] li[lang="fr"]');
    }

    public function testWildcardsAreSearchedLiterally(): void
    {
        $client = static::createClient();
        $page = PageFactory::createOne();
        $page->translate('fr')->setTitle('Taux 100% réel');
        $other = PageFactory::createOne();
        $other->translate('fr')->setTitle('Taux 1000 réel');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $client->request('GET', '/fr/recherche?q='.rawurlencode('100%'));
        self::assertSelectorCount(1, 'section[data-search-group="page"] li');

        $client->request('GET', '/fr/recherche?q='.rawurlencode('0%'));
        self::assertSelectorCount(1, 'section[data-search-group="page"] li', '« % » n\'est pas un joker');
    }

    public function testDraftAndUnpublishedContentIsNeverReturned(): void
    {
        $client = static::createClient();
        $draft = PageFactory::new()->create(['status' => PublicationStatus::Draft]);
        $draft->translate('fr')->setTitle('Secret Zorglub');
        $future = PageFactory::new()->create(['publishedAt' => new \DateTimeImmutable('+10 days')]);
        $future->translate('fr')->setTitle('Futur Zorglub');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $client->request('GET', '/fr/recherche?q=zorglub');

        self::assertSelectorNotExists('section[data-search-group]');
        self::assertSelectorTextContains('main', 'Aucun résultat');
    }

    public function testEmptyAndTooShortQueries(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/recherche');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Saisissez un mot');

        $client->request('GET', '/fr/recherche?q=a');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'au moins 2 caractères');
    }

    public function testQueryIsEscapedInThePage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/fr/recherche?q='.rawurlencode('<script>alert(1)</script>'));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('main script'));
    }

    private function retitle(ArticleTranslation $translation, string $title): void
    {
        $translation->setTitle($title);
    }
}
