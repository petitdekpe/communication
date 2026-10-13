<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\ArticleTranslation;
use App\Entity\ConsultationContribution;
use App\Entity\ServiceRequest;
use App\Enum\AdminRole;
use App\Enum\RequestStatus;
use App\Factory\ArticleFactory;
use App\Factory\ConsultationFactory;
use App\Factory\ServiceFactory;
use App\Factory\SettingFactory;
use App\Retention\PersonalDataPurger;
use App\ServiceRequest\Accreditation\AccreditationForm;
use App\Settings\SiteSettings;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le strict minimum des parcours de l'administration « demandes reçues », conservation et traductions :
 * un test par critère de fin de lot.
 */
final class DemandesRecuesTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    public function testPressOfficerProcessesAnAccreditationFromReceptionToDecision(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Press);
        $service = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $request = new ServiceRequest('MCM-2026-BOUTENBOUT', $service, 'amina@example.org', 'fr', ['lastName' => 'Ouedraogo'], new \DateTimeImmutable());
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->persist($request);
        $manager->flush();
        $path = '/admin/demandes/'.$request->getId().'/statut';

        $crawler = $client->request('GET', '/admin/demandes/'.$request->getId());
        $token = $this->token($crawler, 'form[action$="/statut"] input[name="_token"]');
        $client->request('POST', $path, ['status' => 'approved', 'note' => 'Carte à retirer au guichet.', '_token' => $token], [], $this->origin());

        self::assertResponseStatusCodeSame(303);
        self::assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        self::assertEmailAddressContains($email, 'to', 'amina@example.org');
        self::assertEmailTextBodyContains($email, 'MCM-2026-BOUTENBOUT');
        self::assertEmailTextBodyContains($email, 'Acceptée');
        self::assertEmailTextBodyNotContains($email, 'guichet');
        $manager->clear();
        $saved = $manager->find(ServiceRequest::class, $request->getId());
        self::assertSame(RequestStatus::Approved, $saved->getStatus());
        self::assertSame('Carte à retirer au guichet.', $saved->getInternalNote());

        // Même statut : le commentaire est enregistré, aucun nouvel e-mail.
        $client->request('POST', $path, ['status' => 'approved', 'note' => 'Retirée.', '_token' => $token], [], $this->origin());
        self::assertQueuedEmailCount(0);
    }

    public function testPurgeRemovesExpiredPersonalDataAndKeepsWhatIsStillBeingProcessed(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $manager = $container->get('doctrine')->getManager();
        /** @var FilesystemOperator $private */
        $private = $container->get('private.storage');
        SettingFactory::createOne(['name' => SiteSettings::RETENTION_REQUESTS, 'values' => ['fr' => '12', 'en' => '12']]);
        SettingFactory::createOne(['name' => SiteSettings::RETENTION_CONTRIBUTIONS, 'values' => ['fr' => '6', 'en' => '6']]);

        $service = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $old = new ServiceRequest('MCM-2026-ANCIENNE', $service, 'a@example.org', 'fr', [], new \DateTimeImmutable('-3 years'));
        $old->setStatus(RequestStatus::Approved);
        $pending = new ServiceRequest('MCM-2026-ENCOURS', $service, 'b@example.org', 'fr', [], new \DateTimeImmutable('-3 years'));
        $recent = new ServiceRequest('MCM-2026-RECENTE', $service, 'c@example.org', 'fr', [], new \DateTimeImmutable());
        $recent->setStatus(RequestStatus::Rejected);
        $consultation = ConsultationFactory::createOne();
        $contribution = new ConsultationContribution($consultation, 'Ancien contributeur', null, 'Texte', new \DateTimeImmutable('-2 years'));
        $private->write('2024/01/contribution.pdf', "%PDF-1.4\nx");
        $contribution->attachFile('2024/01/contribution.pdf', 'c.pdf', 'application/pdf', 10);
        foreach ([$old, $pending, $recent, $contribution] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        // Les dates de dernière modification et de réception sont posées par l'application : on les recule en base.
        $connection = $container->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE service_request SET updated_at = :d WHERE tracking_number <> :recent', ['d' => '2023-01-01 00:00:00', 'recent' => 'MCM-2026-RECENTE']);
        $connection->executeStatement('UPDATE consultation_contribution SET created_at = :d', ['d' => '2024-01-01 00:00:00']);
        $manager->clear();

        $purger = $container->get(PersonalDataPurger::class);
        $dry = $purger->purge(true);
        self::assertSame([1, 1, 0], [$dry->requests, $dry->contributions, $dry->subscribers]);
        self::assertCount(3, $manager->getRepository(ServiceRequest::class)->findAll());

        $report = $purger->purge();
        self::assertSame([1, 1], [$report->requests, $report->contributions]);
        $left = array_map(static fn (ServiceRequest $r): string => $r->getTrackingNumber(), $manager->getRepository(ServiceRequest::class)->findAll());
        sort($left);
        self::assertSame(['MCM-2026-ENCOURS', 'MCM-2026-RECENTE'], $left);
        self::assertFalse($private->fileExists('2024/01/contribution.pdf'));
    }

    public function testTranslatorExportsMissingEnglishVersionsAndImportsThemAfterPreview(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Translator);
        $article = ArticleFactory::new()->frenchOnly()->create();

        $client->request('GET', '/admin/traductions/export.xlf?resource=articles');
        self::assertResponseIsSuccessful();
        $xliff = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('original="articles/'.$article->getId().'"', $xliff);
        $translated = (string) preg_replace('~(resname="title".*?)<target state="new"/>~s', '$1<target>Translated title</target>', $xliff, 1);
        $file = tempnam(sys_get_temp_dir(), 'xlf');
        file_put_contents($file, $translated);

        // Aperçu : rien n'est écrit tant que la validation n'a pas eu lieu.
        $crawler = $client->request('GET', '/admin/traductions');
        $client->request('POST', '/admin/traductions/import', ['_token' => $this->token($crawler, 'form[action$="/import"] input[name="_token"]')], ['xliff' => new UploadedFile($file, 'fr-en.xlf', 'application/xml', null, true)], $this->origin());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'Translated title');
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertNull($manager->find($article::class, $article->getId())?->getTranslation('en'));

        $preview = $client->getCrawler();
        $client->request('POST', '/admin/traductions/import/valider', ['id' => $this->token($preview, 'form[action$="/valider"] input[name="id"]'), '_token' => $this->token($preview, 'form[action$="/valider"] input[name="_token"]')], [], $this->origin());
        self::assertResponseStatusCodeSame(303);
        $manager->clear();
        $english = $manager->find($article::class, $article->getId())?->getTranslation('en');
        self::assertInstanceOf(ArticleTranslation::class, $english);
        self::assertSame('Translated title', $english->getTitle());
        self::assertNotEmpty($english->getSlug());
        unlink($file);
    }
}
