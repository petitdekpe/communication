<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Story\AppStory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class HomeControllerTest extends WebTestCase
{
    public function testRootRedirectsToDefaultLocale(): void
    {
        $client = $this->client();
        $client->request('GET', '/');

        self::assertResponseRedirects('/fr');
    }

    public function testHomeIsRenderedInFrench(): void
    {
        $client = $this->client();
        $client->request('GET', '/fr');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextContains('h1', '[À VALIDER]');
        self::assertSelectorExists('a[href="#contenu"]');
        self::assertSelectorExists('.filet-tricolore[aria-hidden="true"]');
    }

    public function testHomeSectionsAreInTheExpectedOrder(): void
    {
        $client = $this->client();
        $crawler = $client->request('GET', '/fr');

        $main = $crawler->filter('main#contenu');
        self::assertSame(
            ['Accompagner la transformation du secteur', 'Des projets pour faire évoluer le secteur', 'Communiqués récents', 'Services prioritaires', 'À la une', 'Les ressources du Ministère', 'Agenda', 'Chiffres clés', 'Mieux comprendre les médias', 'Espace médias', 'Images, vidéos et archives', 'Lettre d\'information'],
            array_values(array_filter(
                $main->filter('h2')->each(static fn ($h2): string => trim($h2->text())),
                static fn (string $title): bool => !str_starts_with($title, '[À VALIDER]'), // consultation (base de données)
            )),
            'Sections de la page d\'accueil, dans l\'ordre',
        );
        self::assertCount(0, $crawler->filter('main form[role="search"]'), 'Pas de recherche dans l\'ouverture');
        self::assertCount(1, $crawler->filter('main form[method="post"][action="/fr/lettre-information"] input[name="newsletter[email]"]'), 'Formulaire relié à l\'inscription avec double opt-in');
    }

    public function testHomeIsRenderedInEnglish(): void
    {
        $client = $this->client();
        $client->request('GET', '/en');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('a[href="#contenu"]', 'Skip to content');
    }

    public function testUnsupportedLocaleIsNotFound(): void
    {
        $client = $this->client();
        $client->request('GET', '/de');

        self::assertResponseStatusCodeSame(404);
    }

    /** Client HTTP, avec le jeu de données de démonstration (« À la une », communiqués, actualités). */
    private function client(): KernelBrowser
    {
        $client = static::createClient();
        AppStory::load();

        return $client;
    }
}
