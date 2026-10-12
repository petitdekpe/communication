<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StyleguideControllerTest extends WebTestCase
{
    private const array COMPONENTS = ['button', 'badge', 'sectionheading', 'articlecard', 'servicecard', 'documentrow', 'agendaitem', 'statcard', 'officialnotice', 'breadcrumb', 'pagination', 'emptystate', 'hero'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urls(): iterable
    {
        yield 'français' => ['/fr/_styleguide', 'fr'];
        yield 'anglais' => ['/en/_styleguide', 'en'];
    }

    #[DataProvider('urls')]
    public function testStyleguideRendersEveryComponent(string $url, string $locale): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('html[lang="%s"]', $locale));
        foreach (self::COMPONENTS as $id) {
            self::assertCount(1, $crawler->filter(\sprintf('section[aria-labelledby="%s"]', $id)), \sprintf('Section « %s »', $id));
        }

        // Aucune icône échappée (régression : SVG rendu en texte dans les boutons).
        self::assertStringNotContainsString('&lt;svg', (string) $client->getResponse()->getContent());
    }

    public function testRouteIsNotDeclaredForProduction(): void
    {
        $route = static::getContainer()->get('router')->getRouteCollection()->get('app_styleguide.fr');

        self::assertNotNull($route);
        $reflection = new \ReflectionMethod(\App\Controller\StyleguideController::class, 'index');
        $attribute = $reflection->getAttributes(\Symfony\Component\Routing\Attribute\Route::class)[0]->newInstance();
        self::assertSame(['dev', 'test'], $attribute->envs);
    }
}
