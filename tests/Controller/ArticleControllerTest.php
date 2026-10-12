<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Routage localisé des articles, repli sur le français et URL équivalentes (sélecteur FR / EN).
 */
final class ArticleControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testUntranslatedArticleIsServedInFrenchOnTheEnglishSiteWithANotice(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::new()->frenchOnly()->create();
        $french = $article->translate('fr');

        $crawler = $client->request('GET', '/en/news/'.$french->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextSame('[role="note"]', 'Content available in French only.');
        self::assertSelectorExists('article[lang="fr"]', 'Le texte affiché est déclaré en français');
        self::assertSelectorTextSame('h1', (string) $french->getTitle());
        self::assertSame(
            '/fr/actualites/'.$french->getSlug(),
            $crawler->filter('nav[aria-label="Language"] a[hreflang="fr"]')->first()->attr('href'),
        );
    }

    public function testFrenchSiteShowsNoNotice(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::new()->frenchOnly()->create();

        $crawler = $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[role="note"]');
        self::assertSelectorExists('article[lang="fr"]');
        self::assertSame(
            '/en/news/'.$article->translate('fr')->getSlug(),
            $crawler->filter('nav[aria-label="Choix de la langue"] a[hreflang="en"]')->first()->attr('href'),
            'Sans traduction, le lien EN mène au repli (slug français sous /en)',
        );
    }

    public function testTranslatedArticleUsesTranslatedSlugsInBothLanguages(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::createOne();
        $fr = (string) $article->translate('fr')->getSlug();
        $en = (string) $article->translate('en')->getSlug();
        self::assertNotSame($fr, $en);

        $crawler = $client->request('GET', '/en/news/'.$en);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[role="note"]');
        self::assertSelectorExists('article[lang="en"]');
        self::assertSame('/fr/actualites/'.$fr, $crawler->filter('nav[aria-label="Language"] a[hreflang="fr"]')->first()->attr('href'));
        self::assertStringEndsWith('/fr/actualites/'.$fr, (string) $crawler->filter('link[rel="alternate"][hreflang="fr"]')->attr('href'));
        self::assertStringEndsWith('/en/news/'.$en, (string) $crawler->filter('link[rel="alternate"][hreflang="en"]')->attr('href'));
    }

    public function testFrenchSlugUnderEnglishRedirectsToTheTranslatedSlug(): void
    {
        $client = static::createClient();
        $article = ArticleFactory::createOne();

        $client->request('GET', '/en/news/'.$article->translate('fr')->getSlug());

        self::assertResponseRedirects('/en/news/'.$article->translate('en')->getSlug(), 301);
    }

    public function testUnpublishedArticlesAreNotFound(): void
    {
        $client = static::createClient();
        $draft = ArticleFactory::new()->draft()->create();
        $scheduled = ArticleFactory::createOne(['status' => PublicationStatus::Published, 'publishedAt' => new \DateTimeImmutable('+1 day')]);

        foreach ([$draft, $scheduled] as $article) {
            $client->request('GET', '/fr/actualites/'.$article->translate('fr')->getSlug());
            self::assertResponseStatusCodeSame(404);
        }

        $client->request('GET', '/fr/actualites/slug-inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAlternateUrlsKeepTheQueryString(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/fr/recherche?q=presse');

        self::assertResponseIsSuccessful();
        self::assertSame('/en/search?q=presse', $crawler->filter('nav[aria-label="Choix de la langue"] a[hreflang="en"]')->first()->attr('href'));
    }
}
