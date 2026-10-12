<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Service;
use App\Entity\ServiceRequest;
use App\Enum\RequestStatus;
use App\Factory\ServiceFactory;
use App\ServiceRequest\Accreditation\AccreditationForm;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Formulaire d'accréditation, accusé de réception, suivi et protections (honeypot, MIME réel, limiteur).
 */
final class ServiceRequestControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string EMAIL = 'amina.test@example.org';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testValidSubmissionStoresTheRequestPrivatelySendsTheAcknowledgmentAndRedirectsWithoutData(): void
    {
        $client = $this->client();
        $service = $this->accreditation();
        $url = $this->formUrl($service);

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorExists('form[method="post"] input[type="file"][multiple]');

        $this->submit($client, $url, [], [$this->upload($this->pdf(), 'carte.pdf', 'application/pdf'), $this->upload($this->jpeg(), 'photo.jpg', 'image/jpeg')]);

        self::assertResponseStatusCodeSame(303);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringEndsWith('/confirmation', $location);
        self::assertStringNotContainsString('?', $location);
        self::assertStringNotContainsString(self::EMAIL, rawurldecode($location));

        $stored = $this->requests();
        self::assertCount(1, $stored);
        $request = $stored[0];
        self::assertMatchesRegularExpression('/^MCM-\d{4}-[A-Z2-9]{8}$/', $request->getTrackingNumber());
        self::assertSame(self::EMAIL, $request->getEmail());
        self::assertSame(RequestStatus::Received, $request->getStatus());
        self::assertSame('Ouedraogo', $request->getPayload()['lastName']);
        self::assertCount(2, $request->getAttachments());

        /** @var FilesystemOperator $private */
        $private = static::getContainer()->get('private.storage');
        foreach ($request->getAttachments() as $attachment) {
            self::assertTrue($private->fileExists($attachment->getPath()), 'Pièce écrite dans le stockage privé');
            self::assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-f]{32}\.(pdf|jpg)$#', $attachment->getPath(), 'Clé aléatoire, sans le nom d\'origine');
            self::assertFileDoesNotExist(self::projectDir().'/public/'.$attachment->getPath());
        }
        self::assertStringStartsNotWith(self::projectDir().'/public', self::projectDir().'/var/storage/private_test', 'Stockage hors de la racine web');

        self::assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        self::assertEmailAddressContains($email, 'to', self::EMAIL);
        self::assertEmailTextBodyContains($email, $request->getTrackingNumber());
        self::assertEmailHtmlBodyContains($email, '/fr/suivi');

        // La confirmation affiche le numéro depuis la session, jamais depuis l'URL.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="tracking-number"]', $request->getTrackingNumber());
    }

    public function testHoneypotRejectsTheSubmission(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        $this->submit($client, $url, ['website' => 'https://spam.example'], [$this->upload($this->pdf(), 'a.pdf', 'application/pdf')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->requests());
        self::assertQueuedEmailCount(0);
    }

    public function testFileWithForgedTypeIsRejected(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        // Texte brut nommé .pdf et annoncé « application/pdf » par le navigateur : le contenu décide.
        $fake = $this->tempFile('<?php echo "pas un pdf";');
        $this->submit($client, $url, [], [$this->upload($fake, 'carte.pdf', 'application/pdf')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'seuls les fichiers PDF et JPG');
        self::assertSame([], $this->requests());
    }

    public function testExecutableRenamedAsJpegIsRejected(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        $this->submit($client, $url, [], [$this->upload($this->tempFile("MZ\x90\x00binary"), 'photo.jpg', 'image/jpeg')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->requests());
    }

    public function testFileOverFiveMegabytesIsRejected(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        $big = $this->tempFile('%PDF-1.4'.str_repeat('0', 5 * 1024 * 1024 + 10));
        $this->submit($client, $url, [], [$this->upload($big, 'gros.pdf', 'application/pdf')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'trop volumineux');
        self::assertSame([], $this->requests());
    }

    public function testConsentAndAttachmentAreRequired(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        $this->submit($client, $url, ['consent' => null], []);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'accepter le traitement');
        self::assertSelectorTextContains('[role="alert"]', 'au moins une pièce');
        self::assertSame([], $this->requests());
    }

    public function testSubmissionsAreRateLimitedPerIp(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);

        for ($i = 0; $i < 10; ++$i) {
            $this->submit($client, $url, ['consent' => null], []);
            self::assertResponseStatusCodeSame(422);
        }
        $this->submit($client, $url, ['consent' => null], []);

        self::assertSelectorTextContains('[role="alert"]', 'Trop de tentatives');
    }

    public function testFormIsNotFoundForServicesWithoutInternalForm(): void
    {
        $client = $this->client();
        $service = ServiceFactory::createOne(['online' => true, 'formKey' => null]);

        $client->request('GET', '/fr/demarches/'.$service->translate('fr')->getSlug().'/demande');

        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmationWithoutASubmissionRedirectsToTheService(): void
    {
        $client = $this->client();
        $service = $this->accreditation();

        $client->request('GET', $this->formUrl($service).'/confirmation');

        self::assertResponseRedirects('/fr/demarches/'.$service->translate('fr')->getSlug());
    }

    public function testTrackingShowsTheStatusForNumberAndEmailOnly(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);
        $this->submit($client, $url, [], [$this->upload($this->pdf(), 'a.pdf', 'application/pdf')]);
        $number = $this->requests()[0]->getTrackingNumber();

        $client->request('GET', '/fr/suivi');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[method="post"][data-turbo="false"]');
        $crawler = $client->getCrawler();

        // Casse de l'e-mail et espaces ignorés.
        $client->request('POST', '/fr/suivi', ['tracking_lookup' => [
            'number' => strtolower($number),
            'email' => '  '.strtoupper(self::EMAIL),
            'website' => '',
            '_token' => $this->token($crawler, 'tracking_lookup'),
        ]], [], $this->origin());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="tracking-status"]', 'Reçue');

        // Mauvais e-mail ou numéro inconnu : même message, rien n'est révélé.
        foreach ([[$number, 'autre@example.org'], ['MCM-2026-ZZZZZZZZ', self::EMAIL]] as [$wrongNumber, $wrongEmail]) {
            $client->request('POST', '/fr/suivi', ['tracking_lookup' => [
                'number' => $wrongNumber,
                'email' => $wrongEmail,
                'website' => '',
                '_token' => $this->token($crawler, 'tracking_lookup'),
            ]], [], $this->origin());
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('[role="alert"]', 'Aucune demande ne correspond');
            self::assertSelectorNotExists('[data-testid="tracking-status"]');
        }
    }

    public function testTrackingNeverLooksUpFromTheUrl(): void
    {
        $client = $this->client();
        $url = $this->formUrl($this->accreditation());
        $client->request('GET', $url);
        $this->submit($client, $url, [], [$this->upload($this->pdf(), 'a.pdf', 'application/pdf')]);
        $number = $this->requests()[0]->getTrackingNumber();

        $client->request('GET', '/fr/suivi?number='.$number.'&email='.self::EMAIL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="tracking-status"]');
        self::assertSelectorTextNotContains('main', $number);
        $client->request('POST', '/fr/suivi?number='.$number.'&email='.self::EMAIL);
        self::assertSelectorNotExists('[data-testid="tracking-status"]');
    }

    public function testTrackingLookupsAreRateLimited(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $client->request('GET', '/fr/suivi');
        $crawler = $client->getCrawler();

        for ($i = 0; $i < 10; ++$i) {
            $client->request('POST', '/fr/suivi', ['tracking_lookup' => ['number' => 'MCM-2026-AAAAAAA'.$i, 'email' => self::EMAIL, 'website' => '', '_token' => $this->token($crawler, 'tracking_lookup')]], [], $this->origin());
            self::assertResponseStatusCodeSame(422);
        }
        $client->request('POST', '/fr/suivi', ['tracking_lookup' => ['number' => 'MCM-2026-AAAAAAAA', 'email' => self::EMAIL, 'website' => '', '_token' => $this->token($crawler, 'tracking_lookup')]], [], $this->origin());

        self::assertResponseStatusCodeSame(429);
    }

    // ---- aides ----------------------------------------------------------

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        // Compteurs des limiteurs : persistants entre tests (cache sur disque), donc remis à zéro ici.
        static::getContainer()->get('app.rate_limiter_pool')->clear();

        return $client;
    }

    private function accreditation(): Service
    {
        return ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
    }

    private function formUrl(Service $service): string
    {
        return '/fr/demarches/'.$service->translate('fr')->getSlug().'/demande';
    }

    /**
     * @param array<string, mixed> $overrides valeurs de champ (null retire le champ)
     * @param list<UploadedFile>   $files
     */
    private function submit(KernelBrowser $client, string $url, array $overrides, array $files): void
    {
        $crawler = $client->getCrawler();
        $fields = array_replace([
            'lastName' => 'Ouedraogo',
            'firstName' => 'Amina',
            'phone' => '+229 00 00 00 00',
            'mediaOutlet' => 'Radio Test',
            'jobTitle' => 'Reporter',
            'eventName' => 'Conférence de test',
            'email' => self::EMAIL,
            'consent' => '1',
            'website' => '',
            '_token' => $this->token($crawler, 'accreditation_form'),
        ], $overrides);

        $client->request('POST', $url, ['accreditation_form' => array_filter($fields, static fn (mixed $v): bool => null !== $v)], ['accreditation_form' => ['attachments' => $files]], $this->origin());
    }

    /**
     * @return array<string, string>
     */
    private function origin(): array
    {
        return ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/'];
    }

    private function token(Crawler $crawler, string $form): string
    {
        $field = $crawler->filter(\sprintf('input[name="%s[_token]"]', $form));

        return $field->count() > 0 ? (string) $field->attr('value') : '';
    }

    /**
     * @return list<ServiceRequest>
     */
    private function requests(): array
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();

        return $manager->getRepository(ServiceRequest::class)->findAll();
    }

    private function upload(string $path, string $name, string $type): UploadedFile
    {
        return new UploadedFile($path, $name, $type, null, true);
    }

    private function pdf(): string
    {
        return $this->tempFile("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);

        return $this->tempFile((string) ob_get_clean());
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mcm');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $this->tempFiles[] = $path;
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
