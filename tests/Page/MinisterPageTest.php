<?php

declare(strict_types=1);

namespace App\Tests\Page;

use App\Factory\PageFactory;
use App\Factory\SettingFactory;
use App\Settings\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Réseaux sociaux de la Ministre : affichés sous son mot, seulement pour les adresses renseignées.
 */
final class MinisterPageTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSocialLinksAppearUnderTheMessageWhenFilled(): void
    {
        $client = static::createClient();
        $this->minister();
        SettingFactory::createOne(['name' => SiteSettings::MINISTER_X, 'values' => ['fr' => 'https://x.com/ministre']]);

        $crawler = $client->request('GET', '/fr/la-ministre');

        self::assertResponseIsSuccessful();
        self::assertSame('https://x.com/ministre', $crawler->filter('.prose-article ~ ul a')->attr('href'));
        self::assertCount(1, $crawler->filter('.prose-article ~ ul a'));
        self::assertSelectorTextContains('.prose-article ~ h2', 'Suivre la Ministre');
    }

    public function testNothingIsShownWhenNoAddressIsFilled(): void
    {
        $client = static::createClient();
        $this->minister();

        $client->request('GET', '/fr/la-ministre');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.prose-article ~ ul');
        self::assertSelectorNotExists('.prose-article ~ h2');
    }

    private function minister(): void
    {
        PageFactory::new()->withTexts([
            'fr' => ['title' => 'La Ministre', 'body' => '<p>Mot de la Ministre.</p>'],
            'en' => ['title' => 'The Minister', 'body' => '<p>Message from the Minister.</p>'],
        ])->create();
    }
}
