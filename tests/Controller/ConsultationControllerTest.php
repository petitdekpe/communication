<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConsultationContribution;
use App\Enum\PublicationStatus;
use App\Factory\ConsultationFactory;
use App\Factory\OfficialDocumentFactory;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Consultations : liste, fiche, formulaire de contribution (fichier optionnel), résultats publiés par l'administration.
 */
final class ConsultationControllerTest extends WebTestCase
{
    use Factories;
    use FormTestTrait;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'en' => ['en'];
    }

    #[DataProvider('locales')]
    public function testListAndShowPageWithDocumentsAndForm(string $locale): void
    {
        $client = $this->client();
        $consultation = ConsultationFactory::createOne(['documents' => [OfficialDocumentFactory::createOne()]]);
        ConsultationFactory::new()->create(['status' => PublicationStatus::Draft]);
        $slug = $consultation->translate($locale)->getSlug();

        $client->request('GET', \sprintf('/%s/consultations', $locale));
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'main article', 'Les brouillons ne sont pas publics');

        $client->request('GET', \sprintf('/%s/consultations/%s', $locale, $slug));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', (string) $consultation->translate($locale)->getTitle());
        self::assertSelectorCount(2, 'article header time[datetime]', 'Dates d\'ouverture et de clôture');
        self::assertSelectorExists('section[aria-labelledby="documents-consultation"] a[download]');
        self::assertSelectorExists('form[name="contribution"] input[type="file"]');
        foreach (['name', 'organization', 'text', 'file', 'consent'] as $field) {
            self::assertSelectorExists(\sprintf('[name="contribution[%s]"]', $field));
        }
        self::assertSelectorNotExists('section[aria-labelledby="resultats"]', 'Pas de résultats publiés');
    }

    public function testValidContributionWithoutFileIsStored(): void
    {
        $client = $this->client();
        $consultation = ConsultationFactory::createOne();
        $url = '/fr/consultations/'.$consultation->translate('fr')->getSlug();
        $client->request('GET', $url);

        $this->contribute($client, $url, []);

        self::assertResponseStatusCodeSame(303);
        self::assertStringNotContainsString('?', (string) $client->getResponse()->headers->get('Location'));
        $contributions = $this->contributions();
        self::assertCount(1, $contributions);
        self::assertSame('Amina Test', $contributions[0]->getName());
        self::assertNull($contributions[0]->getOrganization());
        self::assertNull($contributions[0]->getFilePath());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'contribution a bien été enregistrée');
    }

    public function testContributionFileGoesToThePrivateStorageAndIsValidatedByContent(): void
    {
        $client = $this->client();
        $consultation = ConsultationFactory::createOne();
        $url = '/fr/consultations/'.$consultation->translate('fr')->getSlug();
        $client->request('GET', $url);

        $this->contribute($client, $url, [], $this->upload($this->pdf(), 'avis.pdf', 'application/pdf'));
        self::assertResponseStatusCodeSame(303);
        $stored = $this->contributions()[0];
        self::assertSame('application/pdf', $stored->getFileMimeType());
        /** @var FilesystemOperator $private */
        $private = static::getContainer()->get('private.storage');
        self::assertTrue($private->fileExists((string) $stored->getFilePath()));
        self::assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-f]{32}\.pdf$#', (string) $stored->getFilePath());

        // Faux PDF : le contenu décide, pas le nom ni le type annoncés.
        $client->request('GET', $url);
        $this->contribute($client, $url, [], $this->upload($this->tempFile('<?php system("id");'), 'avis.pdf', 'application/pdf'));
        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->contributions(), 'Le faux PDF n\'a pas créé de contribution');
    }

    public function testHoneypotAndRequiredFieldsAreEnforced(): void
    {
        $client = $this->client();
        $consultation = ConsultationFactory::createOne();
        $url = '/fr/consultations/'.$consultation->translate('fr')->getSlug();
        $client->request('GET', $url);

        $this->contribute($client, $url, ['website' => 'spam']);
        self::assertResponseStatusCodeSame(422);

        $this->contribute($client, $url, ['name' => '', 'text' => 'court', 'consent' => null]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'trop courte');
        self::assertSelectorTextContains('[role="alert"]', 'accepter le traitement');
        self::assertSame([], $this->contributions());
    }

    public function testContributionsAreRateLimited(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $consultation = ConsultationFactory::createOne();
        $url = '/fr/consultations/'.$consultation->translate('fr')->getSlug();
        $client->request('GET', $url);

        for ($i = 0; $i < 10; ++$i) {
            $this->contribute($client, $url, ['name' => '']);
            self::assertResponseStatusCodeSame(422);
        }
        $this->contribute($client, $url, ['name' => '']);

        self::assertSelectorTextContains('[role="alert"]', 'Trop de tentatives');
    }

    public function testClosedConsultationHasNoFormAndRefusesContributions(): void
    {
        $client = $this->client();
        $consultation = ConsultationFactory::createOne([
            'opensAt' => new \DateTimeImmutable('-60 days'),
            'closesAt' => new \DateTimeImmutable('-10 days'),
        ]);
        $url = '/fr/consultations/'.$consultation->translate('fr')->getSlug();

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="contribution"]');
        self::assertSelectorTextContains('#contribuer ~ p', 'clôturée');

        $client->request('POST', $url, ['contribution' => ['name' => 'x']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->contributions());
    }

    public function testResultsPageIsPublishedByTheAdministration(): void
    {
        $client = $this->client();
        $hidden = ConsultationFactory::createOne(['opensAt' => new \DateTimeImmutable('-60 days'), 'closesAt' => new \DateTimeImmutable('-10 days')]);
        $published = ConsultationFactory::createOne(['opensAt' => new \DateTimeImmutable('-60 days'), 'closesAt' => new \DateTimeImmutable('-10 days'), 'resultsPublished' => true]);

        $client->request('GET', '/fr/consultations/'.$hidden->translate('fr')->getSlug().'/resultats');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/fr/consultations/'.$hidden->translate('fr')->getSlug());
        self::assertSelectorNotExists('section[aria-labelledby="resultats"]');

        $client->request('GET', '/fr/consultations/'.$published->translate('fr')->getSlug());
        self::assertSelectorExists('section[aria-labelledby="resultats"] a[href$="/resultats"]');

        $client->request('GET', '/en/consultations/'.$published->translate('en')->getSlug().'/results');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Consultation results');
        self::assertSelectorTextContains('article', 'Consultation results');
    }

    // ---- aides ----------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides valeurs de champ (null retire le champ)
     */
    private function contribute(KernelBrowser $client, string $url, array $overrides, ?UploadedFile $file = null): void
    {
        $crawler = $client->getCrawler();
        $fields = array_replace([
            'name' => 'Amina Test',
            'organization' => '',
            'text' => 'Ma contribution à la consultation, suffisamment longue.',
            'consent' => '1',
            'website' => '',
            '_token' => $this->token($crawler, 'contribution'),
        ], $overrides);

        $client->request('POST', $url, ['contribution' => array_filter($fields, static fn (mixed $v): bool => null !== $v)], null === $file ? [] : ['contribution' => ['file' => $file]], $this->origin());
    }

    /**
     * @return list<ConsultationContribution>
     */
    private function contributions(): array
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();

        return $manager->getRepository(ConsultationContribution::class)->findAll();
    }
}
