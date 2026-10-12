<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Setting;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Factory\ConsultationFactory;
use App\Factory\EventFactory;
use App\Factory\KeyFigureFactory;
use App\Factory\OfficialDocumentFactory;
use App\Factory\ServiceFactory;
use App\Factory\SettingFactory;
use App\Home\HomeSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Chaque section de l'accueil est alimentée par la base : un contenu créé en base apparaît, un contenu retiré disparaît.
 * Plus aucun fichier de démonstration (fixtures/home.yaml) n'entre en jeu.
 */
final class HomeDatabaseTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'en' => ['en'];
    }

    public function testTheDemoYamlFileAndItsLoaderAreGone(): void
    {
        self::assertFileDoesNotExist(\dirname(__DIR__, 2).'/fixtures/home.yaml');
        self::assertFalse(class_exists('App\Home\HomeContent'));
    }

    #[DataProvider('locales')]
    public function testEverySectionComesFromTheDatabase(string $locale): void
    {
        $client = static::createClient();
        $values = [
            HomeSettings::NOTICE_MESSAGE => ['Avis BDD', 'Notice DB'],
            HomeSettings::HERO_TITLE => ['Titre BDD', 'Title DB'],
            HomeSettings::HERO_LEAD => ['Chapô BDD', 'Lead DB'],
            HomeSettings::NEWSLETTER_TEXT => ['Lettre BDD', 'Letter DB'],
            HomeSettings::NEWSLETTER_CONSENT => ['Consentement BDD', 'Consent DB'],
        ];
        foreach ($values as $name => [$fr, $en]) {
            SettingFactory::createOne(['name' => $name, 'values' => ['fr' => $fr, 'en' => $en]]);
        }
        $index = 'fr' === $locale ? 0 : 1;

        $featured = ArticleFactory::createOne(['featured' => true]);
        ArticleFactory::createOne();
        $document = OfficialDocumentFactory::createOne();
        $event = EventFactory::createOne();
        $service = ServiceFactory::new()->featured()->create();
        $figure = KeyFigureFactory::createOne(['value' => '7.0000']);
        $consultation = ConsultationFactory::createOne();

        $client->request('GET', '/'.$locale);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-controller="official-notice"]', $values[HomeSettings::NOTICE_MESSAGE][$index]);
        self::assertSelectorTextContains('h1', $values[HomeSettings::HERO_TITLE][$index]);
        self::assertSelectorTextContains('main', $values[HomeSettings::HERO_LEAD][$index]);
        self::assertSelectorTextContains('main', $values[HomeSettings::NEWSLETTER_TEXT][$index]);
        self::assertSelectorTextContains('main', $values[HomeSettings::NEWSLETTER_CONSENT][$index]);
        self::assertSelectorTextContains('main', (string) $featured->translate($locale)->getTitle());
        self::assertSelectorTextContains('main', (string) $service->translate($locale)->getTitle());
        self::assertSelectorTextContains('main', (string) $document->translate($locale)->getTitle());
        self::assertSelectorTextContains('main', (string) $event->translate($locale)->getTitle());
        self::assertSelectorTextContains('main', (string) $figure->translate($locale)->getLabel());
        self::assertSelectorTextContains('main', '7');
        self::assertSelectorTextContains('main', (string) $consultation->translate($locale)->getTitle());
        self::assertSelectorExists('main a[href="/'.$locale.'/'.('fr' === $locale ? 'donnees' : 'data').'"]');
        self::assertSelectorExists('a[href*="/consultations/"]');
    }

    #[DataProvider('locales')]
    public function testEmptyDatabaseStillRendersTheHomePageWithoutInventedContent(string $locale): void
    {
        $client = static::createClient();

        $client->request('GET', '/'.$locale);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller="official-notice"]', 'Pas d\'avis sans paramètre');
        self::assertSelectorTextContains('h1', 'fr' === $locale ? 'Ministère de la Communication' : 'Ministry of Communication');
        self::assertSelectorNotExists('#a-la-une', 'Sans contenu en base : rien d\'inventé');
        self::assertSelectorNotExists('#chiffres-cles');
        self::assertSelectorExists('main form[action$="'.('fr' === $locale ? 'lettre-information' : 'newsletter').'"]');
    }

    public function testUnpublishedContentDoesNotAppearOnTheHomePage(): void
    {
        $client = static::createClient();
        ServiceFactory::new()->featured()->create(['status' => PublicationStatus::Draft]);
        KeyFigureFactory::new()->create(['status' => PublicationStatus::Draft]);
        ConsultationFactory::new()->create(['status' => PublicationStatus::Draft]);

        $client->request('GET', '/fr');

        self::assertSelectorNotExists('#a-la-une');
        self::assertSelectorNotExists('#chiffres-cles');
        self::assertSelectorNotExists('a[href*="/consultations/"]');
    }

    public function testSettingChangeIsReflectedWithoutDeploying(): void
    {
        $client = static::createClient();
        SettingFactory::createOne(['name' => HomeSettings::HERO_TITLE, 'values' => ['fr' => 'Premier titre', 'en' => 'First title']]);

        $client->request('GET', '/fr');
        self::assertSelectorTextContains('h1', 'Premier titre');

        $manager = self::getContainer()->get('doctrine')->getManager();
        $setting = $manager->getRepository(Setting::class)->findOneBy(['name' => HomeSettings::HERO_TITLE]);
        $setting?->getTranslation('fr')?->setValue('Second titre');
        $manager->flush();

        $client->request('GET', '/fr');
        self::assertSelectorTextContains('h1', 'Second titre');
    }
}
