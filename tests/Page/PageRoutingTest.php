<?php

declare(strict_types=1);

namespace App\Tests\Page;

use App\Entity\Page;
use App\Enum\PublicationStatus;
use App\Factory\PageFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Route localisée /{_locale}/{path} : une page publiée est résolue par son chemin dans l'arborescence, avec fil d'Ariane
 * automatique ; chemin inconnu ou ancêtre non publié : 404 ; les routes explicites l'emportent.
 */
final class PageRoutingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testNestedPageIsResolvedByItsPathWithBreadcrumb(): void
    {
        $client = static::createClient();
        [$root, $child] = $this->tree();

        $crawler = $client->request('GET', '/fr/le-ministere/nos-missions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nos missions');
        $items = $crawler->filter('nav[aria-label] ol li');
        self::assertCount(3, $items);
        self::assertSame('/fr', $items->eq(0)->filter('a')->attr('href'));
        self::assertSame('/fr/le-ministere', $items->eq(1)->filter('a')->attr('href'));
        self::assertSame('Le ministère', trim($items->eq(1)->text()));
        self::assertSame('Nos missions', trim($items->eq(2)->filter('[aria-current="page"]')->text()));
        self::assertNotNull($root->getId());
        self::assertNotNull($child->getId());
    }

    public function testEnglishPathUsesEnglishSlugsAndHreflangPointsToEachLanguage(): void
    {
        $client = static::createClient();
        $this->tree();

        $crawler = $client->request('GET', '/en/the-ministry/our-missions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Our missions');
        self::assertSame('http://localhost/fr/le-ministere/nos-missions', $crawler->filter('link[rel="alternate"][hreflang="fr"]')->attr('href'));
        self::assertSame('http://localhost/en/the-ministry/our-missions', $crawler->filter('link[rel="alternate"][hreflang="en"]')->attr('href'));
    }

    public function testFrenchSlugOnEnglishUrlRedirectsToCanonicalAddress(): void
    {
        $client = static::createClient();
        $this->tree();

        $client->request('GET', '/en/le-ministere/nos-missions');
        self::assertResponseRedirects('/en/the-ministry/our-missions', 301);

        $client->request('GET', '/fr/le-ministere/nos-missions/');
        self::assertResponseRedirects('/fr/le-ministere/nos-missions', 301);
    }

    public function testUnknownPathAndWrongParentAreNotFound(): void
    {
        $client = static::createClient();
        $this->tree();

        foreach (['/fr/inconnue', '/fr/nos-missions', '/fr/le-ministere/inconnue', '/fr/le-ministere/nos-missions/trop'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testChildOfAnUnpublishedPageIsNotReachable(): void
    {
        $client = static::createClient();
        $this->tree(['status' => PublicationStatus::Draft, 'publishedAt' => null]);

        $client->request('GET', '/fr/le-ministere/nos-missions');
        self::assertResponseStatusCodeSame(404);
    }

    public function testExplicitRoutesWinOverPages(): void
    {
        $client = static::createClient();
        PageFactory::createOne($this->texts('Actualités', 'News'));

        $client->request('GET', '/fr/actualites');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('main article h1');
    }

    /**
     * @param array<string, mixed> $rootAttributes
     *
     * @return array{Page, Page}
     */
    private function tree(array $rootAttributes = []): array
    {
        $root = PageFactory::createOne($rootAttributes + $this->texts('Le ministère', 'The ministry'));
        $child = PageFactory::createOne(['parent' => $root] + $this->texts('Nos missions', 'Our missions'));

        return [$root, $child];
    }

    /**
     * @return array{texts: array<string, array<string, string>>}
     */
    private function texts(string $fr, string $en): array
    {
        return ['texts' => ['fr' => ['title' => $fr], 'en' => ['title' => $en]]];
    }
}
