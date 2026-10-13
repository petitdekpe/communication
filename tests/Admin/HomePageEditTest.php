<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Enum\AdminRole;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Édition des textes de l'accueil : enregistrement, puis affichage sur la page publique.
 */
final class HomePageEditTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    public function testAdminEditsHeroTexts(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);

        $crawler = $client->request('GET', '/admin/accueil');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'home_page[heroTitleFr]' => 'Titre <strong>de test</strong><br>ligne 2<script>alert(1)</script>',
            'home_page[heroLeadFr]' => 'Chapô de test',
            'home_page[heroTitleEn]' => 'Test title',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/accueil');

        $client->request('GET', '/fr');
        self::assertSelectorExists('h1 strong');
        self::assertSelectorExists('h1 br');
        self::assertSelectorNotExists('h1 script');
        self::assertSelectorTextContains('h1', 'de test');
        self::assertSelectorTextContains('main', 'Chapô de test');

        $client->request('GET', '/en');
        self::assertSelectorTextContains('h1', 'Test title');
    }
}
