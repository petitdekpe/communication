<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Enum\AdminRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Matrice des droits : pour chaque rôle, les pages autorisées (200) et refusées (403), et l'accès anonyme (redirection).
 */
final class AdminAccessTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    /**
     * Page => rôles qui y ont accès (tous les autres reçoivent 403).
     *
     * @return array<string, list<AdminRole>>
     */
    private static function matrix(): array
    {
        $content = [AdminRole::SuperAdmin, AdminRole::Admin, AdminRole::Reviewer, AdminRole::Editor, AdminRole::Translator];

        return [
            '/admin' => [...$content, AdminRole::Press],
            '/admin/liste/articles' => $content,
            '/admin/liste/documents' => $content,
            '/admin/liste/events' => $content,
            '/admin/liste/key-figures' => $content,
            '/admin/liste/consultations' => $content,
            '/admin/liste/galleries' => $content,
            '/admin/liste/videos' => $content,
            '/admin/liste/services' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/liste/pages' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/liste/menus' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/liste/contributions' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/liste/subscribers' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/liste/requests' => [AdminRole::SuperAdmin, AdminRole::Admin, AdminRole::Press],
            '/admin/liste/settings' => [AdminRole::SuperAdmin],
            '/admin/liste/users' => [AdminRole::SuperAdmin],
            '/admin/audit' => [AdminRole::SuperAdmin, AdminRole::Admin],
            '/admin/articles/nouveau' => [AdminRole::SuperAdmin, AdminRole::Admin, AdminRole::Reviewer, AdminRole::Editor],
            '/admin/documents/nouveau' => [AdminRole::SuperAdmin, AdminRole::Admin, AdminRole::Reviewer, AdminRole::Editor],
            '/admin/utilisateurs/nouveau' => [AdminRole::SuperAdmin],
            '/admin/recherche?q=test' => [...$content, AdminRole::Press],
        ];
    }

    /**
     * @return iterable<string, array{AdminRole}>
     */
    public static function roles(): iterable
    {
        foreach ([AdminRole::SuperAdmin, AdminRole::Admin, AdminRole::Reviewer, AdminRole::Editor, AdminRole::Translator, AdminRole::Press] as $role) {
            yield $role->value => [$role];
        }
    }

    /** Un seul utilisateur par rôle : toutes les pages de la matrice sont essayées, 200 ou 403 selon le rôle. */
    #[DataProvider('roles')]
    public function testRoleAccess(AdminRole $role): void
    {
        $client = $this->client();
        $this->loginAs($client, $role);

        $failures = [];
        foreach (self::matrix() as $url => $allowed) {
            $client->request('GET', $url);
            $expected = \in_array($role, $allowed, true) ? 200 : 403;
            if ($expected !== $client->getResponse()->getStatusCode()) {
                $failures[] = \sprintf('%s : %d attendu, %d reçu', $url, $expected, $client->getResponse()->getStatusCode());
            }
        }

        self::assertSame([], $failures, 'Droits incorrects pour '.$role->value);
    }

    public function testAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $client = $this->client();

        $failures = [];
        foreach ([...array_keys(self::matrix()), '/admin/2fa', '/admin/securite/double-authentification'] as $url) {
            $client->request('GET', $url);
            $location = (string) $client->getResponse()->headers->get('Location');
            if (!$client->getResponse()->isRedirect() || !str_ends_with($location, '/admin/login')) {
                $failures[] = $url.' -> '.$client->getResponse()->getStatusCode().' '.$location;
            }
        }

        self::assertSame([], $failures);
    }

    public function testAnonymousPostsAreRefusedToo(): void
    {
        $client = $this->client();

        foreach (['/admin/liste/articles/1/publish', '/admin/liste/articles/groupe', '/admin/langue', '/admin/utilisateurs/1/reinitialiser-2fa', '/admin/demandes/1/statut'] as $url) {
            $client->request('POST', $url);
            self::assertTrue($client->getResponse()->isRedirect(), $url);
        }
    }

    public function testRoleHierarchyIsAsDocumented(): void
    {
        $client = $this->client();
        $hierarchy = static::getContainer()->get('security.role_hierarchy');

        $reachable = static fn (string $role): array => $hierarchy->getReachableRoleNames([$role]);

        self::assertContains('ROLE_ADMIN', $reachable('ROLE_SUPER_ADMIN'));
        foreach (['ROLE_REVIEWER', 'ROLE_EDITOR', 'ROLE_TRANSLATOR', 'ROLE_PRESS'] as $role) {
            self::assertContains($role, $reachable('ROLE_ADMIN'), 'ADMIN couvre '.$role);
        }
        self::assertContains('ROLE_EDITOR', $reachable('ROLE_REVIEWER'));
        self::assertNotContains('ROLE_REVIEWER', $reachable('ROLE_EDITOR'));
        self::assertNotContains('ROLE_ADMIN', $reachable('ROLE_REVIEWER'));
        self::assertNotContains('ROLE_TRANSLATOR', $reachable('ROLE_EDITOR'));
        self::assertNotContains('ROLE_PRESS', $reachable('ROLE_REVIEWER'));
        self::assertNotContains('ROLE_SUPER_ADMIN', $reachable('ROLE_ADMIN'));
        unset($client);
    }

    public function testMenuOnlyListsWhatTheRoleCanOpen(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Press);
        $client->request('GET', '/admin');
        self::assertSelectorExists('nav#menu-principal a[href="/admin/liste/requests"]');
        self::assertSelectorNotExists('nav#menu-principal a[href="/admin/liste/articles"]');
        self::assertSelectorNotExists('nav#menu-principal a[href="/admin/liste/users"]');
        self::assertSelectorNotExists('nav#menu-principal a[href="/admin/audit"]');

        self::ensureKernelShutdown();
        $client = $this->client();
        $this->loginAs($client, AdminRole::Translator);
        $client->request('GET', '/admin');
        self::assertSelectorExists('nav#menu-principal a[href="/admin/liste/articles"]');
        self::assertSelectorNotExists('nav#menu-principal a[href="/admin/liste/requests"]');

        self::ensureKernelShutdown();
        $client = $this->client();
        $this->loginAs($client, AdminRole::SuperAdmin);
        $crawler = $client->request('GET', '/admin');
        self::assertSelectorExists('nav#menu-principal a[href="/admin/liste/users"]');
        self::assertSelectorExists('nav#menu-principal a[href="/admin/audit"]');
        $groups = $crawler->filter('nav#menu-principal section h2')->each(static fn ($h): string => trim($h->text()));
        self::assertSame(['Contenus', 'Services', 'Médias', 'Demandes reçues', 'Structure du site', 'Utilisateurs'], $groups);
    }
}
