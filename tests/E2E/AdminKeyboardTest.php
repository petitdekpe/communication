<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Enum\AdminRole;
use App\Factory\ArticleFactory;
use App\Factory\UserFactory;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Gabarit d'administration dans un vrai Chrome, au clavier seul : connexion avec double authentification, lien
 * d'évitement, parcours de tous les éléments interactifs avec focus visible, barre latérale repliable, boîte de
 * confirmation, onglets de langue ; puis contrôle WCAG 2.2 AA (axe-core) des pages principales.
 */
#[ResetDatabase]
final class AdminKeyboardTest extends PantherTestCase
{
    use ViewportTrait;

    private const string AXE_SCRIPT = __DIR__.'/../Resources/axe.min.js';

    public function testAdminTemplateIsFullyOperableWithTheKeyboard(): void
    {
        $client = $this->signIn();

        // 1. Lien d'évitement : premier élément atteint, visible au focus, déplace le focus sur le contenu.
        $client->request('GET', '/admin');
        $client->waitFor('#contenu');
        $this->press($client, WebDriverKeys::TAB);
        self::assertSame('Aller au contenu', trim((string) $client->executeScript('return document.activeElement.textContent;')));
        self::assertGreaterThan(0, $client->executeScript('return document.activeElement.getBoundingClientRect().width;'), 'Lien d\'évitement visible au focus');
        $this->press($client, WebDriverKeys::ENTER);
        self::assertSame('contenu', $client->executeScript('return document.activeElement.id;'));

        // 2. Tous les éléments interactifs sont atteignables au clavier, avec un indicateur de focus visible.
        $this->assertEveryInteractiveElementIsReachable($client);

        // 3. Barre latérale : le bouton se commande au clavier, l'état est annoncé, les liens restent nommés.
        $client->executeScript('document.querySelector("[data-sidebar-target=toggle]").focus();');
        self::assertSame('true', $client->executeScript('return document.querySelector("[data-sidebar-target=toggle]").getAttribute("aria-expanded");'));
        $this->press($client, WebDriverKeys::ENTER);
        self::assertSame('false', $client->executeScript('return document.querySelector("[data-sidebar-target=toggle]").getAttribute("aria-expanded");'));
        self::assertSame('true', $client->executeScript('return document.querySelector("[data-controller~=sidebar]").dataset.collapsed;'));
        self::assertLessThan(120, $client->executeScript('return document.getElementById("barre-laterale").getBoundingClientRect().width;'), 'Barre repliée');
        self::assertSame(0, $client->executeScript('return Array.from(document.querySelectorAll("#menu-principal a")).filter((a) => a.textContent.trim() === "").length;'), 'Chaque lien garde un nom accessible');
        $this->press($client, WebDriverKeys::ENTER);
        self::assertSame('true', $client->executeScript('return document.querySelector("[data-sidebar-target=toggle]").getAttribute("aria-expanded");'));

        // 4. Liste : confirmation de suppression dans un <dialog>, annulable au clavier, focus rendu au bouton.
        $client->request('GET', '/admin/liste/articles');
        $client->waitFor('button[data-action="confirm-dialog#ask"]');
        $trigger = $client->findElement(WebDriverBy::cssSelector('button[data-action="confirm-dialog#ask"]'));
        $triggerId = $trigger->getAttribute('data-confirm-dialog-form-param');
        $client->executeScript('document.querySelector("button[data-action=\'confirm-dialog#ask\']").focus();');
        $this->press($client, WebDriverKeys::ENTER);
        $client->waitFor('dialog[open]');
        self::assertTrue($client->executeScript('return document.querySelector("dialog[open]").contains(document.activeElement);'), 'Focus dans la boîte');
        self::assertSame('Annuler', trim((string) $client->executeScript('return document.activeElement.textContent;')), 'Focus initial sur l\'action sûre');
        $this->press($client, WebDriverKeys::ESCAPE);
        $client->wait()->until(static fn () => false === $client->executeScript('return !!document.querySelector("dialog[open]");'));
        self::assertSame($triggerId, $client->executeScript('return document.activeElement.dataset.confirmDialogFormParam;'), 'Le focus revient au bouton déclencheur');
        self::assertSame(20, $client->executeScript('return document.querySelectorAll("tbody tr").length;'), 'Rien n\'a été supprimé');

        // 5. Sélection multiple au clavier : la barre d'actions groupées s'active.
        $client->executeScript('document.querySelector("input[data-table-select-target=all]").focus();');
        $this->press($client, ' ');
        self::assertSame(false, $client->executeScript('return document.querySelector("[data-table-select-target=submit]").disabled;'));
        self::assertStringContainsString('20', (string) $client->executeScript('return document.querySelector("[data-table-select-target=count]").textContent;'));

        // 6. Onglets de langue : flèches, un seul onglet dans l'ordre de tabulation.
        $articleId = (int) $client->executeScript('return document.querySelector("tbody tr a").href.split("/").pop();');
        $client->request('GET', '/admin/articles/'.$articleId);
        $client->waitFor('[role="tablist"]');
        $client->executeScript('document.getElementById("tab-fr").focus();');
        $this->press($client, WebDriverKeys::ARROW_RIGHT);
        self::assertSame('tab-en', $client->executeScript('return document.activeElement.id;'));
        self::assertSame('true', $client->executeScript('return document.getElementById("tab-en").getAttribute("aria-selected");'));
        self::assertTrue($client->executeScript('return document.getElementById("panel-fr").hidden;'));
        self::assertFalse($client->executeScript('return document.getElementById("panel-en").hidden;'));
        $this->press($client, WebDriverKeys::HOME);
        self::assertSame('tab-fr', $client->executeScript('return document.activeElement.id;'));
    }

    public function testAdminPagesHaveNoWcagViolation(): void
    {
        $client = $this->signIn();
        $pages = ['/admin', '/admin/liste/articles', '/admin/liste/users', '/admin/articles/nouveau', '/admin/utilisateurs/nouveau', '/admin/audit', '/admin/recherche?q=a'];
        $violations = [];

        foreach ([1280, 360] as $width) {
            $this->setViewport($client, $width, 900);
            foreach ($pages as $url) {
                $client->request('GET', $url);
                $client->waitFor('#contenu');
                self::assertLessThanOrEqual($width, $client->executeScript('return document.documentElement.scrollWidth;'), 'Pas de débordement horizontal : '.$url.' à '.$width.' px');
                $report = $this->runAxe($client);
                if ('' !== $report) {
                    $violations[] = \sprintf("%s (%d px)\n%s", $url, $width, $report);
                }
            }
        }

        self::assertSame([], $violations, implode("\n\n", $violations));
    }

    public function testAuthenticationPagesHaveNoWcagViolation(): void
    {
        self::bootKernel();
        $user = UserFactory::new()->withRole(AdminRole::SuperAdmin)->create();
        self::ensureKernelShutdown();
        $client = static::createPantherClient();
        $this->forgetSession($client);
        $violations = [];

        foreach (['/admin/login', '/admin/mot-de-passe-oublie'] as $url) {
            $client->request('GET', $url);
            $client->waitFor('#contenu');
            $report = $this->runAxe($client);
            if ('' !== $report) {
                $violations[] = $url."\n".$report;
            }
        }
        // Formulaire de code TOTP (état « mot de passe validé »).
        $this->fillLogin($client, (string) $user->getEmail());
        $client->waitFor('#_auth_code');
        $report = $this->runAxe($client);
        if ('' !== $report) {
            $violations[] = "/admin/2fa\n".$report;
        }

        self::assertSame([], $violations, implode("\n\n", $violations));
    }

    // ---- aides ----------------------------------------------------------

    /** Crée les données, puis ouvre une session par le vrai parcours : mot de passe puis code TOTP. */
    private function signIn(): Client
    {
        self::bootKernel();
        $user = UserFactory::new()->withRole(AdminRole::SuperAdmin)->create();
        ArticleFactory::createMany(25);
        self::ensureKernelShutdown();

        $client = static::createPantherClient();
        $this->setViewport($client, 1280, 900);
        $this->forgetSession($client);
        $this->fillLogin($client, (string) $user->getEmail());
        $client->waitFor('#_auth_code');
        $client->findElement(WebDriverBy::id('_auth_code'))->sendKeys(UserFactory::currentCode().WebDriverKeys::ENTER);
        $client->waitFor('#barre-laterale');

        return $client;
    }

    /** Le navigateur est partagé entre les tests : on repart sans cookie de session d'un test précédent. */
    private function forgetSession(Client $client): void
    {
        $client->request('GET', '/admin/login');
        $client->getCookieJar()->clear();
    }

    private function fillLogin(Client $client, string $email): void
    {
        $client->request('GET', '/admin/login');
        $client->waitFor('#email');
        $client->findElement(WebDriverBy::id('email'))->sendKeys($email);
        $client->findElement(WebDriverBy::id('password'))->sendKeys(UserFactory::PASSWORD.WebDriverKeys::ENTER);
    }

    private function press(Client $client, string $key): void
    {
        $this->webDriver($client)->executeCustomCommand('/session/:sessionId/actions', 'POST', [
            'actions' => [['type' => 'key', 'id' => 'clavier', 'actions' => [
                ['type' => 'keyDown', 'value' => $key],
                ['type' => 'keyUp', 'value' => $key],
            ]]],
        ]);
    }

    private function assertEveryInteractiveElementIsReachable(Client $client): void
    {
        $expected = $client->executeScript(<<<'JS'
            const selector = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';
            let index = 0;
            for (const el of document.querySelectorAll(selector)) {
                if (el.closest('dialog:not([open]), [inert], details:not([open]) > :not(summary)') || el.getClientRects().length === 0) continue;
                if (getComputedStyle(el).visibility === 'hidden') continue;
                el.dataset.focusIndex = String(index++);
            }
            return index;
            JS);
        self::assertGreaterThan(10, $expected);

        $visited = [];
        for ($i = 0; $i < $expected + 5; ++$i) {
            $this->press($client, WebDriverKeys::TAB);
            $result = $client->executeScript(<<<'JS'
                const el = document.activeElement;
                if (el.dataset.focusIndex === undefined) return null;
                const style = getComputedStyle(el);
                const label = (el.textContent || el.id || el.tagName).trim().replace(/\s+/g, ' ').slice(0, 40);
                if (style.outlineStyle === 'none') return [el.dataset.focusIndex, `Focus invisible sur « ${label} »`];
                if (parseFloat(style.outlineWidth) < 2) return [el.dataset.focusIndex, `Anneau de focus trop fin sur « ${label} »`];
                return [el.dataset.focusIndex, ''];
                JS);
            if (!\is_array($result)) {
                continue;
            }
            [$index, $problem] = $result;
            self::assertSame('', $problem);
            self::assertIsString($index);
            $visited[$index] = true;
        }

        self::assertCount($expected, $visited, 'Tous les éléments interactifs du gabarit sont atteignables au clavier');
    }

    private function runAxe(Client $client): string
    {
        $axe = file_get_contents(self::AXE_SCRIPT);
        self::assertIsString($axe, 'tests/Resources/axe.min.js introuvable');
        $client->executeScript($axe);

        $report = $client->executeAsyncScript(<<<'JS'
            const done = arguments[arguments.length - 1];
            axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']}})
                .then((results) => done(results.violations.map((v) =>
                    `- ${v.id} (${v.impact})\n` + v.nodes.map((n) => `    ${n.target.join(' ')} : ${n.failureSummary.replace(/\s+/g, ' ')}`).join('\n')
                ).join('\n')))
                .catch((error) => done(`- axe-error : ${error}`));
            JS);
        self::assertIsString($report);

        return $report;
    }
}
