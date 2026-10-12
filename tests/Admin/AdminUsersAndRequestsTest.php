<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\AuditLog;
use App\Entity\ResetPasswordRequest;
use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestAttachment;
use App\Entity\User;
use App\Enum\AdminRole;
use App\Enum\RequestStatus;
use App\Factory\ServiceFactory;
use App\Factory\UserFactory;
use App\ServiceRequest\Accreditation\AccreditationForm;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Comptes (super administrateur), connexion et réinitialisation de mot de passe, demandes de presse.
 */
final class AdminUsersAndRequestsTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    private function html(?\Symfony\Component\Mime\RawMessage $message): ?string
    {
        return $message instanceof \Symfony\Component\Mime\Email ? $message->getHtmlBody() : null;
    }

    private function text(?\Symfony\Component\Mime\RawMessage $message): ?string
    {
        return $message instanceof \Symfony\Component\Mime\Email ? $message->getTextBody() : null;
    }

    public function testLoginErrorIsTheSameForUnknownWrongAndDisabledAccounts(): void
    {
        $client = $this->client();
        $active = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $disabled = UserFactory::new()->withRole(AdminRole::Admin)->inactive()->create();

        $messages = [];
        foreach ([['inconnu@example.org', 'whatever-password'], [(string) $active->getEmail(), 'mauvais-mot-de-passe'], [(string) $disabled->getEmail(), UserFactory::PASSWORD]] as [$email, $password]) {
            $this->submitLogin($client, $email, $password);
            self::assertResponseRedirects('/admin/login');
            $client->followRedirect();
            $messages[] = trim($client->getCrawler()->filter('[role="alert"]')->text());
        }

        self::assertSame(['Identifiants invalides.', 'Identifiants invalides.', 'Identifiants invalides.'], $messages);
        $client->request('GET', '/admin');
        $this->assertRedirectsToLogin($client);
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();

        for ($i = 0; $i < 6; ++$i) {
            $this->submitLogin($client, (string) $user->getEmail(), 'mauvais-mot-de-passe-'.$i);
        }
        // Même le bon mot de passe est refusé tant que la limite est atteinte.
        $this->submitLogin($client, (string) $user->getEmail());
        $client->request('GET', '/admin');

        $this->assertRedirectsToLogin($client);
    }

    public function testPasswordsAreHashedAndNeverStoredInClear(): void
    {
        $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();

        self::assertStringStartsWith('$', $user->getPassword());
        self::assertStringNotContainsString(UserFactory::PASSWORD, $user->getPassword());
    }

    public function testPasswordResetByEmailKeepsTwoFactorAndHidesUnknownAccounts(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();

        // Compte inconnu : même page de confirmation, aucun message envoyé.
        $crawler = $client->request('GET', '/admin/mot-de-passe-oublie');
        $client->request('POST', '/admin/mot-de-passe-oublie', ['forgot_password' => ['email' => 'inconnu@example.org', '_token' => $this->token($crawler, 'input[name="forgot_password[_token]"]')]], [], $this->origin());
        self::assertResponseRedirects('/admin/mot-de-passe-oublie/verification');
        self::assertQueuedEmailCount(0);

        // Compte connu : un message avec un lien.
        $crawler = $client->request('GET', '/admin/mot-de-passe-oublie');
        $client->request('POST', '/admin/mot-de-passe-oublie', ['forgot_password' => ['email' => strtoupper((string) $user->getEmail()), '_token' => $this->token($crawler, 'input[name="forgot_password[_token]"]')]], [], $this->origin());
        self::assertResponseRedirects('/admin/mot-de-passe-oublie/verification');
        self::assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        self::assertEmailAddressContains($email, 'to', (string) $user->getEmail());
        preg_match('#http://localhost(/admin/reinitialisation/[A-Za-z0-9]+)#', (string) $this->html($email), $matches);
        self::assertNotEmpty($matches, 'Lien de réinitialisation dans le message');

        // Le jeton quitte l'adresse (redirection), puis le formulaire s'affiche.
        $client->request('GET', $matches[1]);
        self::assertResponseRedirects('/admin/reinitialisation');
        $crawler = $client->followRedirect();
        $new = 'un-nouveau-mot-de-passe-solide';
        $client->request('POST', '/admin/reinitialisation', ['new_password' => ['plainPassword' => ['first' => $new, 'second' => $new], '_token' => $this->token($crawler, 'input[name="new_password[_token]"]')]], [], $this->origin());
        self::assertResponseRedirects('/admin/login');

        // Le nouveau mot de passe fonctionne, mais la double authentification reste exigée.
        $this->submitLogin($client, (string) $user->getEmail(), $new);
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/2fa');
        // Le lien ne sert qu'une fois.
        $client->request('GET', $matches[1]);
        $client->followRedirect();
        self::assertResponseRedirects('/admin/mot-de-passe-oublie');
    }

    public function testResetFormRejectsShortAndMismatchedPasswords(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $crawler = $client->request('GET', '/admin/mot-de-passe-oublie');
        $client->request('POST', '/admin/mot-de-passe-oublie', ['forgot_password' => ['email' => $user->getEmail(), '_token' => $this->token($crawler, 'input[name="forgot_password[_token]"]')]], [], $this->origin());
        preg_match('#(/admin/reinitialisation/[A-Za-z0-9]+)#', (string) $this->text($this->getMailerMessage()), $matches);
        $client->request('GET', $matches[1]);
        $crawler = $client->followRedirect();
        $token = $this->token($crawler, 'input[name="new_password[_token]"]');

        $client->request('POST', '/admin/reinitialisation', ['new_password' => ['plainPassword' => ['first' => 'court', 'second' => 'court'], '_token' => $token]], [], $this->origin());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', '12 caractères');

        $client->request('POST', '/admin/reinitialisation', ['new_password' => ['plainPassword' => ['first' => 'un-mot-de-passe-assez-long', 'second' => 'un-autre-mot-de-passe-long'], '_token' => $token]], [], $this->origin());
        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, static::getContainer()->get('doctrine')->getManager()->getRepository(ResetPasswordRequest::class)->findAll(), 'Le jeton reste valable');
    }

    public function testSuperAdminCreatesAnInvitedUserAndCannotLockThemselvesOut(): void
    {
        $client = $this->client();
        $super = $this->loginAs($client, AdminRole::SuperAdmin);

        $crawler = $client->request('GET', '/admin/utilisateurs/nouveau');
        $client->request('POST', '/admin/utilisateurs/nouveau', ['user_admin' => [
            'name' => 'Nouvelle Relectrice',
            'email' => 'Relectrice@Example.org',
            'roles' => ['ROLE_REVIEWER'],
            'active' => '1',
            '_token' => $this->token($crawler, 'input[name="user_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);

        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $created = $manager->getRepository(User::class)->findOneBy(['email' => 'relectrice@example.org']);
        self::assertNotNull($created, 'Adresse enregistrée en minuscules');
        self::assertSame(['ROLE_REVIEWER', 'ROLE_USER'], $created->getRoles());
        self::assertFalse($created->isTotpAuthenticationEnabled());
        self::assertQueuedEmailCount(1);
        self::assertEmailAddressContains($this->getMailerMessage(), 'to', 'relectrice@example.org');

        // Les rôles se limitent à ceux de l'énumération.
        $crawler = $client->request('GET', '/admin/utilisateurs/'.$created->getId());
        $client->request('POST', '/admin/utilisateurs/'.$created->getId(), ['user_admin' => ['name' => 'X', 'email' => 'relectrice@example.org', 'roles' => ['ROLE_REVIEWER', 'ROLE_ROOT'], '_token' => $this->token($crawler, 'input[name="user_admin[_token]"]')]], [], $this->origin());
        self::assertResponseStatusCodeSame(422);

        // Sur son propre compte : rôles et activation verrouillés.
        $crawler = $client->request('GET', '/admin/utilisateurs/'.$super->getId());
        self::assertSelectorExists('input[name="user_admin[active]"][disabled]');
        self::assertSelectorExists('input[name="user_admin[roles][]"][disabled]');
        $client->request('POST', '/admin/utilisateurs/'.$super->getId(), ['user_admin' => ['name' => $super->getName(), 'email' => $super->getEmail(), 'roles' => ['ROLE_EDITOR'], '_token' => $this->token($crawler, 'input[name="user_admin[_token]"]')]], [], $this->origin());
        $manager->clear();
        self::assertContains('ROLE_SUPER_ADMIN', $manager->find(User::class, $super->getId())?->getRoles() ?? []);
        self::assertTrue($manager->find(User::class, $super->getId())?->isActive());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::SuperAdmin);
        $existing = UserFactory::new()->withRole(AdminRole::Editor)->create();

        $crawler = $client->request('GET', '/admin/utilisateurs/nouveau');
        $client->request('POST', '/admin/utilisateurs/nouveau', ['user_admin' => ['name' => 'Doublon', 'email' => strtoupper((string) $existing->getEmail()), 'roles' => ['ROLE_EDITOR'], '_token' => $this->token($crawler, 'input[name="user_admin[_token]"]')]], [], $this->origin());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'déjà utilisée');
    }

    public function testResettingSomeoneElsesTwoFactorRequiresConfirmationTokenAndIsAudited(): void
    {
        $client = $this->client();
        $super = $this->loginAs($client, AdminRole::SuperAdmin);
        $target = UserFactory::new()->withRole(AdminRole::Editor)->create();

        $crawler = $client->request('GET', '/admin/utilisateurs/'.$target->getId());
        self::assertSelectorExists('dialog[aria-labelledby="confirm-title"]');
        self::assertSelectorExists('button[data-action="confirm-dialog#ask"][data-confirm-dialog-form-param="reset-2fa"]');
        $token = $this->token($crawler, 'form#reset-2fa input[name="_token"]');

        $client->request('POST', '/admin/utilisateurs/'.$target->getId().'/reinitialiser-2fa', ['_token' => 'faux'], [], $this->origin());
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/utilisateurs/'.$target->getId().'/reinitialiser-2fa', ['_token' => $token], [], $this->origin());
        self::assertResponseStatusCodeSame(303);
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertNull($manager->find(User::class, $target->getId())?->getTotpSecret());

        $log = $manager->getRepository(AuditLog::class)->findOneBy(['entityClass' => 'User', 'entityId' => $target->getId(), 'action' => 'update']);
        self::assertNotNull($log);
        self::assertSame($super->getUserIdentifier(), $log->getActor());
        self::assertSame('***', $log->getChanges()['totpSecret'][0], 'Le secret TOTP n\'est jamais enregistré dans le journal');
        self::assertNull($log->getChanges()['totpSecret'][1]);

        // Pas sur son propre compte.
        $client->request('POST', '/admin/utilisateurs/'.$super->getId().'/reinitialiser-2fa', ['_token' => 'x'], [], $this->origin());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAuditNeverStoresPasswordHashes(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::SuperAdmin);
        $crawler = $client->request('GET', '/admin/utilisateurs/nouveau');
        $client->request('POST', '/admin/utilisateurs/nouveau', ['user_admin' => ['name' => 'Audit', 'email' => 'audit@example.org', 'roles' => ['ROLE_PRESS'], 'active' => '1', '_token' => $this->token($crawler, 'input[name="user_admin[_token]"]')]], [], $this->origin());

        $manager = static::getContainer()->get('doctrine')->getManager();
        $created = $manager->getRepository(User::class)->findOneBy(['email' => 'audit@example.org']);
        $log = $manager->getRepository(AuditLog::class)->findOneBy(['entityClass' => 'User', 'entityId' => $created?->getId(), 'action' => 'create']);
        self::assertNotNull($log);
        self::assertSame('***', $log->getChanges()['password'][1]);
        self::assertStringNotContainsString((string) $created?->getPassword(), json_encode($log->getChanges(), \JSON_THROW_ON_ERROR));
    }

    public function testPressOfficerHandlesRequestsAndDownloadsAttachmentsSafely(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Press);
        $service = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $request = new ServiceRequest('MCM-2026-TESTTEST', $service, 'amina@example.org', 'fr', ['lastName' => 'Ouedraogo', 'firstName' => 'Amina', 'phone' => null, 'mediaOutlet' => 'Radio Test', 'jobTitle' => 'Reporter', 'eventName' => 'Conférence'], new \DateTimeImmutable());
        $manager = static::getContainer()->get('doctrine')->getManager();
        /** @var FilesystemOperator $private */
        $private = static::getContainer()->get('private.storage');
        $private->write('2026/10/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf', "%PDF-1.4\nsecret");
        $attachment = new ServiceRequestAttachment($request, '2026/10/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf', '../../../etc/passwd<script>.pdf', 'application/pdf', 14);
        $manager->persist($request);
        $manager->flush();

        // Liste, fiche, statut.
        $client->request('GET', '/admin/liste/requests');
        self::assertSelectorTextContains('tbody', 'MCM-2026-TESTTEST');
        $crawler = $client->request('GET', '/admin/demandes/'.$request->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Ouedraogo');
        self::assertSelectorTextContains('main', 'Radio Test');

        $client->request('POST', '/admin/demandes/'.$request->getId().'/statut', ['status' => 'approved', '_token' => $this->token($crawler, 'form[action$="/statut"] input[name="_token"]')], [], $this->origin());
        self::assertResponseStatusCodeSame(303);
        $manager->clear();
        self::assertSame(RequestStatus::Approved, $manager->find(ServiceRequest::class, $request->getId())?->getStatus());
        $log = $manager->getRepository(AuditLog::class)->findOneBy(['entityClass' => 'ServiceRequest', 'action' => 'update']);
        self::assertSame(['received', 'approved'], $log?->getChanges()['status']);

        // Statut inconnu ou jeton faux : refus.
        $client->request('POST', '/admin/demandes/'.$request->getId().'/statut', ['status' => 'hacked', '_token' => $this->token($crawler, 'form[action$="/statut"] input[name="_token"]')], [], $this->origin());
        self::assertResponseStatusCodeSame(403);

        // Pièce jointe : en téléchargement, jamais interprétée, nom neutralisé.
        $client->request('GET', '/admin/demandes/'.$request->getId().'/pieces/'.$attachment->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringStartsWith('attachment', $disposition);
        self::assertStringNotContainsString('passwd', $disposition);
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));

        // Une pièce d'une autre demande, ou inconnue : 404.
        $client->request('GET', '/admin/demandes/'.$request->getId().'/pieces/99999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditorAndReviewerCannotReachRequestsOrTheirAttachments(): void
    {
        $client = $this->client();
        $service = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $request = new ServiceRequest('MCM-2026-NOACCESS', $service, 'x@example.org', 'fr', [], new \DateTimeImmutable());
        static::getContainer()->get('doctrine')->getManager()->persist($request);
        static::getContainer()->get('doctrine')->getManager()->flush();

        foreach ([AdminRole::Editor, AdminRole::Reviewer, AdminRole::Translator] as $role) {
            $user = $this->user($role);
            $client->loginUser($user, 'admin');
            foreach (['/admin/demandes/'.$request->getId(), '/admin/demandes/'.$request->getId().'/pieces/1'] as $url) {
                $client->request('GET', $url);
                self::assertResponseStatusCodeSame(403, $role->value.' '.$url);
            }
            $client->request('POST', '/admin/demandes/'.$request->getId().'/statut', ['status' => 'approved', '_token' => 'x'], [], $this->origin());
            self::assertResponseStatusCodeSame(403, $role->value);
        }
    }
}
