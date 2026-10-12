<?php

declare(strict_types=1);

namespace App\Tests\Page;

use App\Enum\PublicationStatus;
use App\Factory\PageFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Plan du site HTML et sitemap.xml générés depuis l'arborescence des pages publiées, avec les alternates hreflang.
 */
final class SitemapTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSitemapXmlListsPublishedPagesWithHreflangAlternates(): void
    {
        $client = static::createClient();
        $root = PageFactory::createOne(['texts' => ['fr' => ['title' => 'Le ministère'], 'en' => ['title' => 'The ministry']]]);
        PageFactory::createOne(['parent' => $root, 'texts' => ['fr' => ['title' => 'Nos missions'], 'en' => ['title' => 'Our missions']]]);
        PageFactory::createOne(['status' => PublicationStatus::Draft, 'publishedAt' => null, 'texts' => ['fr' => ['title' => 'Brouillon secret']]]);
        PageFactory::createOne(['locales' => ['fr'], 'texts' => ['fr' => ['title' => 'Seulement français']]]);

        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/xml', (string) $client->getResponse()->headers->get('Content-Type'));
        $xml = (string) $client->getResponse()->getContent();
        $document = new \SimpleXMLElement($xml);
        $document->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $document->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');

        $locations = array_map('strval', $document->xpath('//s:url/s:loc') ?: []);
        self::assertContains('http://localhost/fr', $locations);
        self::assertContains('http://localhost/fr/le-ministere/nos-missions', $locations);
        self::assertContains('http://localhost/en/the-ministry/our-missions', $locations);
        self::assertNotContains('http://localhost/fr/brouillon-secret', $locations);
        self::assertNotContains('http://localhost/en/seulement-francais', $locations);

        $entry = $document->xpath('//s:url[s:loc="http://localhost/fr/le-ministere/nos-missions"]/x:link') ?: [];
        $alternates = [];
        foreach ($entry as $link) {
            $alternates[(string) $link['hreflang']] = (string) $link['href'];
        }
        self::assertSame([
            'fr' => 'http://localhost/fr/le-ministere/nos-missions',
            'en' => 'http://localhost/en/the-ministry/our-missions',
            'x-default' => 'http://localhost/fr/le-ministere/nos-missions',
        ], $alternates);

        $frenchOnly = $document->xpath('//s:url[s:loc="http://localhost/fr/seulement-francais"]/x:link') ?: [];
        self::assertCount(2, $frenchOnly, 'fr + x-default seulement');
    }

    public function testHtmlSiteMapShowsTheTreeInEachLanguage(): void
    {
        $client = static::createClient();
        $root = PageFactory::createOne(['texts' => ['fr' => ['title' => 'Le ministère'], 'en' => ['title' => 'The ministry']]]);
        PageFactory::createOne(['parent' => $root, 'texts' => ['fr' => ['title' => 'Nos missions'], 'en' => ['title' => 'Our missions']]]);

        $crawler = $client->request('GET', '/fr/plan-du-site');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Plan du site');
        self::assertSame('/fr/le-ministere/nos-missions', $crawler->filter('a:contains("Nos missions")')->attr('href'));
        self::assertSelectorExists('a[href="/fr/actualites"]');

        $crawler = $client->request('GET', '/en/sitemap');
        self::assertResponseIsSuccessful();
        self::assertSame('/en/the-ministry/our-missions', $crawler->filter('a:contains("Our missions")')->attr('href'));
    }
}
