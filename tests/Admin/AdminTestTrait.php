<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\User;
use App\Enum\AdminRole;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Aides des tests d'administration : un utilisateur par rôle, connexion rapide (loginUser) ou parcours réel
 * (mot de passe puis code TOTP), jeton CSRF, en-têtes d'origine.
 */
trait AdminTestTrait
{
    private function client(): KernelBrowser
    {
        $client = static::createClient();
        // Compteurs des limiteurs (formulaires publics et connexion) : persistants entre tests, remis à zéro ici.
        static::getContainer()->get('app.rate_limiter_pool')->clear();
        static::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function user(AdminRole ...$roles): User
    {
        return UserFactory::new()->withRole(...$roles)->create();
    }

    /** Connexion directe (sans formulaire) : pour les tests de droits, où la double authentification n'est pas le sujet. */
    private function loginAs(KernelBrowser $client, AdminRole ...$roles): User
    {
        $user = $this->user(...$roles);
        $client->loginUser($user, 'admin');

        return $user;
    }

    /**
     * Parcours réel : formulaire de connexion. Retourne la réponse de la connexion (redirection vers /admin/2fa
     * si le compte a un secret TOTP, vers /admin sinon).
     */
    private function submitLogin(KernelBrowser $client, string $email, string $password = UserFactory::PASSWORD): void
    {
        $crawler = $client->request('GET', '/admin/login');
        $client->request('POST', '/admin/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => (string) $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ], [], $this->origin());
    }

    private function submitTotp(KernelBrowser $client, string $code): void
    {
        $crawler = $client->request('GET', '/admin/2fa');
        $client->request('POST', '/admin/2fa_check', [
            '_auth_code' => $code,
            '_csrf_token' => (string) $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ], [], $this->origin());
    }

    /**
     * @return array<string, string>
     */
    private function origin(): array
    {
        return ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/admin'];
    }

    private function token(Crawler $crawler, string $selector): string
    {
        return (string) $crawler->filter($selector)->attr('value');
    }

    /** Vrai si la réponse est une redirection vers la page de connexion. */
    private function assertRedirectsToLogin(KernelBrowser $client): void
    {
        self::assertTrue($client->getResponse()->isRedirect(), 'Redirection attendue, statut '.$client->getResponse()->getStatusCode());
        self::assertStringEndsWith('/admin/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
