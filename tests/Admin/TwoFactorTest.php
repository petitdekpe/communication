<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\User;
use App\Enum\AdminRole;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * La double authentification ne peut pas être contournée : sans code TOTP valide (ou sans enrôlement), aucune page
 * d'administration, aucune action (GET ou POST) n'est accessible.
 */
final class TwoFactorTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    /** Adresses sensibles essayées avec un jeton « 2FA en cours » : toutes doivent renvoyer vers le formulaire de code. */
    private const array GUARDED = [
        '/admin',
        '/admin/liste/articles',
        '/admin/liste/users',
        '/admin/audit',
        '/admin/articles/nouveau',
        '/admin/utilisateurs/nouveau',
        '/admin/recherche?q=test',
    ];

    public function testPasswordAloneOpensNothingWhenTheAccountHasTwoFactor(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::SuperAdmin)->create();

        $this->submitLogin($client, (string) $user->getEmail());
        self::assertResponseRedirects('/admin');

        $failures = [];
        foreach (self::GUARDED as $url) {
            $client->request('GET', $url);
            $location = (string) $client->getResponse()->headers->get('Location');
            if (!$client->getResponse()->isRedirect() || !str_ends_with($location, '/admin/2fa')) {
                $failures[] = $url.' -> '.$client->getResponse()->getStatusCode().' '.$location;
            }
        }
        // Actions d'écriture, avec ou sans jeton : jamais exécutées.
        foreach (['/admin/liste/articles/1/delete', '/admin/liste/articles/groupe', '/admin/utilisateurs/1/reinitialiser-2fa', '/admin/langue'] as $url) {
            $client->request('POST', $url, ['_token' => 'x'], [], $this->origin());
            $location = (string) $client->getResponse()->headers->get('Location');
            if (!$client->getResponse()->isRedirect() || !str_ends_with($location, '/admin/2fa')) {
                $failures[] = 'POST '.$url.' -> '.$client->getResponse()->getStatusCode();
            }
        }

        self::assertSame([], $failures);
    }

    public function testWrongCodeKeepsTheDoorClosedAndRightCodeOpensIt(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $this->submitLogin($client, (string) $user->getEmail());

        $wrong = '000000' === UserFactory::currentCode() ? '111111' : '000000';
        $this->submitTotp($client, $wrong);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Code invalide');
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/2fa');

        // Un code mal formé ou vide ne passe pas non plus.
        foreach (['', '12345', 'abcdef'] as $bad) {
            $this->submitTotp($client, $bad);
            $client->request('GET', '/admin');
            self::assertResponseRedirects('/admin/2fa', null, 'Code « '.$bad.' »');
        }

        $this->submitTotp($client, UserFactory::currentCode());
        self::assertResponseRedirects('/admin');
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/admin/liste/articles');
        self::assertResponseIsSuccessful();
    }

    /** Les essais de code ratés comptent dans la limitation de connexion du pare-feu : pas de force brute sur les 6 chiffres. */
    public function testRepeatedWrongCodesLockTheAttemptsEvenForTheRightCode(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $this->submitLogin($client, (string) $user->getEmail());

        $wrong = '000000' === UserFactory::currentCode() ? '111111' : '000000';
        for ($i = 0; $i < 5; ++$i) {
            $this->submitTotp($client, $wrong);
        }
        $this->submitTotp($client, UserFactory::currentCode());

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringEndsWith('/admin/2fa', (string) $client->getResponse()->headers->get('Location'));
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/2fa');
    }

    public function testPendingUserCannotReplaceTheirSecretThroughTheSetupPage(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $secret = (string) $user->getTotpSecret();
        $this->submitLogin($client, (string) $user->getEmail());

        // Mot de passe validé, code pas encore saisi : la page d'enrôlement est inaccessible.
        $client->request('GET', '/admin/securite/double-authentification');
        self::assertResponseRedirects('/admin/2fa');
        $client->request('POST', '/admin/securite/double-authentification', ['code' => '123456', '_token' => 'x'], [], $this->origin());
        self::assertResponseRedirects('/admin/2fa');

        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertSame($secret, $manager->find(User::class, $user->getId())?->getTotpSecret());
    }

    public function testFullyAuthenticatedUserWithASecretCannotReplaceItEither(): void
    {
        $client = $this->client();
        $user = $this->loginAs($client, AdminRole::Admin);
        $secret = $user->getTotpSecret();

        $client->request('GET', '/admin/securite/double-authentification');

        self::assertResponseRedirects('/admin');
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertSame($secret, $manager->find(User::class, $user->getId())?->getTotpSecret());
    }

    public function testAccountWithoutASecretIsForcedToEnrolBeforeAnythingElse(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::SuperAdmin)->withoutTwoFactor()->create();

        $this->submitLogin($client, (string) $user->getEmail());
        self::assertResponseRedirects('/admin');

        $failures = [];
        foreach ([...self::GUARDED, '/admin/liste/requests', '/admin/audit'] as $url) {
            $client->request('GET', $url);
            if (!$client->getResponse()->isRedirect() || !str_ends_with((string) $client->getResponse()->headers->get('Location'), '/admin/securite/double-authentification')) {
                $failures[] = $url.' -> '.$client->getResponse()->getStatusCode();
            }
        }
        foreach (['/admin/liste/articles/1/delete', '/admin/utilisateurs/1/reinitialiser-2fa'] as $url) {
            $client->request('POST', $url, ['_token' => 'x'], [], $this->origin());
            if (!$client->getResponse()->isRedirect() || !str_ends_with((string) $client->getResponse()->headers->get('Location'), '/admin/securite/double-authentification')) {
                $failures[] = 'POST '.$url.' -> '.$client->getResponse()->getStatusCode();
            }
        }
        self::assertSame([], $failures, 'Rien n\'est accessible tant que la double authentification n\'est pas enrôlée');

        // Seuls l'enrôlement et la déconnexion restent ouverts.
        $client->request('GET', '/admin/securite/double-authentification');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="totp-secret"]');
    }

    public function testEnrolmentStoresTheSecretOnlyAfterAValidFirstCode(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::SuperAdmin)->withoutTwoFactor()->create();
        $this->submitLogin($client, (string) $user->getEmail());

        $crawler = $client->request('GET', '/admin/securite/double-authentification');
        $secret = str_replace(' ', '', trim($crawler->filter('[data-testid="totp-secret"]')->text()));
        $token = $this->token($crawler, 'input[name="_token"]');
        self::assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $secret);

        // Mauvais code : rien n'est enregistré.
        $client->request('POST', '/admin/securite/double-authentification', ['code' => '000000', '_token' => $token], [], $this->origin());
        self::assertResponseStatusCodeSame(422);
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertNull($manager->find(User::class, $user->getId())?->getTotpSecret());

        // Sans jeton CSRF : refusé.
        $client->request('POST', '/admin/securite/double-authentification', ['code' => \OTPHP\TOTP::createFromSecret($secret)->now()], [], $this->origin());
        self::assertResponseStatusCodeSame(403);

        // Bon code : enrôlé, l'administration s'ouvre.
        $client->request('POST', '/admin/securite/double-authentification', ['code' => \OTPHP\TOTP::createFromSecret($secret)->now(), '_token' => $token], [], $this->origin());
        self::assertResponseRedirects('/admin');
        $manager->clear();
        self::assertSame($secret, $manager->find(User::class, $user->getId())?->getTotpSecret());
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testNextLoginAfterEnrolmentAsksForTheCode(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();

        $this->submitLogin($client, (string) $user->getEmail());
        $this->submitTotp($client, UserFactory::currentCode());
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/logout');
        $client->request('GET', '/admin');
        $this->assertRedirectsToLogin($client);

        // Nouvelle connexion : le mot de passe ne suffit plus.
        $this->submitLogin($client, (string) $user->getEmail());
        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/2fa');
    }

    public function testCheckEndpointWithoutAPendingLoginDoesNothing(): void
    {
        $client = $this->client();

        $client->request('POST', '/admin/2fa_check', ['_auth_code' => UserFactory::currentCode()], [], $this->origin());
        $client->request('GET', '/admin');

        $this->assertRedirectsToLogin($client);
    }

    public function testLastAccessIsSetOnlyOnceFullyAuthenticated(): void
    {
        $client = $this->client();
        $user = UserFactory::new()->withRole(AdminRole::Admin)->create();
        $manager = static::getContainer()->get('doctrine')->getManager();

        $this->submitLogin($client, (string) $user->getEmail());
        $manager->clear();
        self::assertNull($manager->find(User::class, $user->getId())?->getLastLoginAt(), 'Mot de passe seul : pas encore d\'accès');

        $this->submitTotp($client, UserFactory::currentCode());
        $manager->clear();
        self::assertNotNull($manager->find(User::class, $user->getId())?->getLastLoginAt());
    }
}
