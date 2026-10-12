<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Factory\ServiceFactory;
use App\ServiceRequest\Accreditation\AccreditationForm;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Parcours complet de l'accréditation presse dans un vrai Chrome : liste, fiche, formulaire avec pièces jointes,
 * confirmation avec numéro de suivi, puis consultation du statut avec le numéro et l'e-mail.
 * À chaque étape, l'adresse de la page ne contient aucune donnée personnelle ni le numéro de suivi.
 */
#[ResetDatabase]
final class AccreditationJourneyTest extends PantherTestCase
{
    private const string LAST_NAME = 'Ouedraogo';
    private const string EMAIL = 'amina.ouedraogo@example.org';
    private const string EVENT = 'Conférence de test';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testFullAccreditationJourney(): void
    {
        // Compteurs des limiteurs partagés avec le serveur de Panther (cache sur disque) : remise à zéro.
        self::bootKernel();
        self::getContainer()->get('app.rate_limiter_pool')->clear();
        self::ensureKernelShutdown();

        $service = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $title = (string) $service->translate('fr')->getTitle();
        $visited = [];

        $client = static::createPantherClient();

        // 1. La démarche est dans la liste, puis sa fiche affiche l'encadré récapitulatif.
        $client->request('GET', '/fr/demarches');
        $visited[] = $client->getCurrentURL();
        $client->clickLink($title);
        $visited[] = $client->getCurrentURL();
        $client->waitFor('aside[aria-labelledby="recapitulatif"]');
        self::assertCount(2, $client->getCrawler()->filter('section[aria-labelledby="pieces-requises"] input[type="checkbox"]'));

        // 2. Le formulaire interne.
        $client->clickLink('Faire la demande en ligne');
        $client->waitFor('form[name="accreditation_form"]');
        $visited[] = $client->getCurrentURL();

        $this->type($client, 'accreditation_form_lastName', self::LAST_NAME);
        $this->type($client, 'accreditation_form_firstName', 'Amina');
        $this->type($client, 'accreditation_form_phone', '+229 00 00 00 00');
        $this->type($client, 'accreditation_form_mediaOutlet', 'Radio Test');
        $this->type($client, 'accreditation_form_jobTitle', 'Reporter');
        $this->type($client, 'accreditation_form_eventName', self::EVENT);
        $this->type($client, 'accreditation_form_email', self::EMAIL);
        $client->findElement(WebDriverBy::id('accreditation_form_attachments'))
            ->sendKeys($this->pdf()."\n".$this->jpeg());
        $client->findElement(WebDriverBy::id('accreditation_form_consent'))->click();

        $client->findElement(WebDriverBy::cssSelector('form[name="accreditation_form"] button[type="submit"]'))->click();

        // 3. Confirmation : numéro de suivi affiché (lu en session, jamais dans l'URL).
        $client->waitFor('[data-testid="tracking-number"]');
        $visited[] = $client->getCurrentURL();
        $number = $client->getCrawler()->filter('[data-testid="tracking-number"]')->text();
        self::assertMatchesRegularExpression('/^MCM-\d{4}-[A-Z2-9]{8}$/', $number);
        self::assertStringEndsWith('/confirmation', $client->getCurrentURL());

        // 4. Suivi avec le numéro et l'e-mail.
        $client->clickLink('Suivre ma demande');
        $client->waitFor('form[name="tracking_lookup"]');
        $visited[] = $client->getCurrentURL();
        $this->type($client, 'tracking_lookup_number', $number);
        $this->type($client, 'tracking_lookup_email', self::EMAIL);
        $client->findElement(WebDriverBy::cssSelector('form[name="tracking_lookup"] button[type="submit"]'))->click();

        $client->waitFor('[data-testid="tracking-status"]');
        $visited[] = $client->getCurrentURL();
        self::assertSame('Reçue', $client->getCrawler()->filter('[data-testid="tracking-status"]')->text());
        self::assertStringEndsWith('/fr/suivi', $client->getCurrentURL());

        // Données personnelles jamais dans une URL.
        foreach ($visited as $url) {
            $decoded = rawurldecode($url);
            foreach ([self::LAST_NAME, self::EMAIL, 'amina', self::EVENT, $number] as $secret) {
                self::assertStringNotContainsStringIgnoringCase($secret, $decoded, 'Donnée personnelle dans l\'URL '.$url);
            }
            self::assertStringNotContainsString('?', $url, 'Aucune query string dans le parcours : '.$url);
        }
    }

    private function type(Client $client, string $id, string $value): void
    {
        $client->findElement(WebDriverBy::id($id))->sendKeys($value);
    }

    private function pdf(): string
    {
        return $this->tempFile("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n", 'carte.pdf');
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);

        return $this->tempFile((string) ob_get_clean(), 'photo.jpg');
    }

    private function tempFile(string $content, string $name): string
    {
        $dir = sys_get_temp_dir().'/mcm-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/'.$name;
        file_put_contents($path, $content);

        return $this->tempFiles[] = $path;
    }
}
