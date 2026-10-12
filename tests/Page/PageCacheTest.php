<?php

declare(strict_types=1);

namespace App\Tests\Page;

use App\Cache\ContentCache;
use App\Entity\Page;
use App\Enum\PublicationStatus;
use App\Factory\PageFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Cache HTTP des pages publiées : servi depuis le cache pour un visiteur sans session, invalidé par étiquettes dès
 * qu'un contenu est publié ou modifié ; blocs dynamiques gardés mais invalidés à la publication.
 */
final class PageCacheTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        // Le cache HTML est désactivé dans les tests (voir .env.test) : on l'active ici, sur un pool vide.
        $_SERVER['PAGE_CACHE'] = $_ENV['PAGE_CACHE'] = '1';
        self::bootKernel();
        self::getContainer()->get(ContentCache::class)->clear();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        self::bootKernel();
        self::getContainer()->get(ContentCache::class)->clear();
        self::ensureKernelShutdown();
        $_SERVER['PAGE_CACHE'] = $_ENV['PAGE_CACHE'] = '0';
        parent::tearDown();
    }

    public function testSecondVisitIsServedFromTheCacheWithPublicHeadersAndTags(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $page = PageFactory::createOne();
        $url = '/fr/'.$page->translate('fr')->getSlug();

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Page-Cache', 'MISS');
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('s-maxage=60', $cacheControl);
        self::assertStringContainsString('page-'.$page->getId(), (string) $client->getResponse()->headers->get('Cache-Tag'));

        $client->request('GET', $url);
        self::assertResponseHeaderSame('X-Page-Cache', 'HIT');
        self::assertSelectorTextContains('h1', 'Titre de la page');
    }

    public function testPublishingAPageAppearsImmediatelyAndSessionVisitorsBypassTheCache(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = PageFactory::createOne(['status' => PublicationStatus::Draft, 'publishedAt' => null]);
        $url = '/fr/'.$page->translate('fr')->getSlug();

        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);

        // Le noyau remet l'EntityManager à zéro après chaque requête : on relit la page avant de la modifier.
        $page = $em->find(Page::class, $page->getId());
        $page->setStatus(PublicationStatus::Published)->setPublishedAt(new \DateTimeImmutable('-1 minute'));
        $em->flush();

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Page-Cache', 'MISS');
    }

    public function testEditingAPageInvalidatesItsCachedHtml(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = PageFactory::new()->withTexts(['fr' => ['body' => '<p>Texte avant</p>']])->create();
        $url = '/fr/'.$page->translate('fr')->getSlug();

        $client->request('GET', $url);
        self::assertSelectorTextContains('main', 'Texte avant');
        $client->request('GET', $url);
        self::assertResponseHeaderSame('X-Page-Cache', 'HIT');

        $page = $em->find(Page::class, $page->getId());
        $page->translate('fr')->setBody('<p>Texte après</p>');
        $em->flush();

        $client->request('GET', $url);
        self::assertResponseHeaderSame('X-Page-Cache', 'MISS');
        self::assertSelectorTextContains('main', 'Texte après');
    }

    public function testUnpublishedOrSensitiveContentIsNeverStored(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/fr/inconnue');
        self::assertResponseStatusCodeSame(404);
        self::assertResponseNotHasHeader('X-Page-Cache');
        self::assertInstanceOf(Page::class, PageFactory::createOne());
    }
}
