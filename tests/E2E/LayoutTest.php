<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Story\AppStory;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;
use Zenstruck\Foundry\Attribute\WithStory;

/**
 * Gabarit (en-tête, menus, recherche, pied de page) dans un vrai Chrome :
 *  - accessibilité WCAG 2.2 A/AA (dont contraste) avec axe-core, dans chaque état d'interface ;
 *  - utilisation au clavier seul ;
 *  - affichage sans débordement horizontal de 360 à 1440 px.
 */
#[ResetDatabase]
#[WithStory(AppStory::class)]
final class LayoutTest extends PantherTestCase
{
    use ViewportTrait;

    private const string AXE_SCRIPT = __DIR__.'/../Resources/axe.min.js';
    private const string SCREENSHOT_DIR = __DIR__.'/../../var/panther';

    // ---- axe-core ---------------------------------------------------------

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function axeScenarios(): iterable
    {
        foreach ([360, 768, 1024, 1440] as $width) {
            yield $width.' px, page' => [$width, 'page'];
            yield $width.' px, recherche ouverte' => [$width, 'search'];
        }
        yield '1024 px, sous-menu ouvert' => [1024, 'submenu'];
        yield '1440 px, sous-menu ouvert' => [1440, 'submenu'];
        yield '360 px, menu mobile ouvert' => [360, 'mobile-menu'];
        yield '768 px, menu mobile ouvert' => [768, 'mobile-menu'];
    }

    #[DataProvider('axeScenarios')]
    public function testLayoutHasNoWcagViolation(int $width, string $state): void
    {
        foreach (['/fr', '/en'] as $url) {
            $client = $this->openAt($url, $width);

            match ($state) {
                'search' => $this->chord($client, WebDriverKeys::CONTROL, 'k'),
                'submenu' => $client->executeScript('document.getElementById("nav-1-bouton").click()'),
                'mobile-menu' => $client->executeScript(
                    'document.querySelector("[data-mobile-menu-target=trigger]").click();'
                    .'document.querySelector("#menu-mobile [aria-controls=menu-mobile-1]").click();'
                ),
                default => null,
            };

            $this->screenshot($client, \sprintf('etat-%s-%s-%d', $state, trim($url, '/'), $width));
            self::assertSame('', $this->runAxe($client), \sprintf('Violations axe sur %s (%d px, %s)', $url, $width, $state));
        }
    }

    /**
     * Le styleguide affiche chaque composant dans toutes ses variantes : un passage d'axe y couvre
     * le contraste de toutes les combinaisons de couleurs utilisées par les composants.
     */
    #[DataProvider('keyboardWidths')]
    public function testStyleguideHasNoWcagViolation(int $width): void
    {
        foreach (['/fr/_styleguide', '/en/_styleguide'] as $url) {
            $client = $this->openAt($url, $width);

            self::assertSame('', $this->runAxe($client), \sprintf('Violations axe sur %s (%d px)', $url, $width));
            self::assertLessThanOrEqual($width, $client->executeScript('return document.documentElement.scrollWidth;'), 'Débordement horizontal');
        }
    }

    // ---- Affichage de 360 à 1440 px ----------------------------------------

    /**
     * @return iterable<string, array{int}>
     */
    public static function widths(): iterable
    {
        foreach ([360, 390, 768, 1024, 1280, 1440] as $width) {
            yield $width.' px' => [$width];
        }
    }

    #[DataProvider('widths')]
    public function testLayoutFitsViewport(int $width): void
    {
        foreach (['/fr', '/en'] as $url) {
            $client = $this->openAt($url, $width);

            self::assertSame($width, $client->executeScript('return window.innerWidth;'), 'Émulation du viewport');
            self::assertLessThanOrEqual(
                $width,
                $client->executeScript('return document.documentElement.scrollWidth;'),
                \sprintf('Débordement horizontal sur %s à %d px', $url, $width),
            );

            $isDesktop = $width >= 1024;
            self::assertSame($isDesktop, $this->isVisible($client, 'nav[data-controller="main-nav"]'), 'Menu desktop');
            self::assertSame(!$isDesktop, $this->isVisible($client, '[data-mobile-menu-target="trigger"]'), 'Bouton Menu mobile');
            self::assertTrue($this->isVisible($client, 'header img[alt]'), 'Logo');
            self::assertTrue($client->executeScript(<<<'JS'
                const identity = document.querySelector('header a').getBoundingClientRect();
                const actions = [...document.querySelectorAll('header .container-site > div button')]
                    .map((b) => b.getBoundingClientRect()).filter((r) => r.width > 0);
                return actions.every((r) => r.left >= identity.right);
                JS), 'Le bloc identité ne chevauche pas les boutons');

            $logoHeight = $client->executeScript('return document.querySelector("header img").getBoundingClientRect().height;');
            self::assertEquals($isDesktop ? 48 : 40, $logoHeight, 'Hauteur du logo');

            $utilityHeight = $client->executeScript('return document.querySelector(".surface-dark").getBoundingClientRect().height;');
            self::assertEquals(36, $utilityHeight, 'Hauteur de la barre utilitaire');

            $this->screenshot($client, \sprintf('layout-%s-%d', trim($url, '/'), $width));
        }
    }

    // ---- Clavier ----------------------------------------------------------

    public function testSkipLinksAreFirstAndWork(): void
    {
        $client = $this->openAt('/fr', 1440);

        $this->press($client, WebDriverKeys::TAB);
        self::assertSame('#contenu', $this->activeAttribute($client, 'getAttribute("href")'));
        self::assertGreaterThan(1, $client->executeScript('return document.activeElement.getBoundingClientRect().width;'), 'Lien d\'évitement visible au focus');

        $this->press($client, WebDriverKeys::ENTER);
        self::assertSame('contenu', $this->activeAttribute($client, 'id'));

        $client = $this->openAt('/fr', 1440);
        $this->press($client, WebDriverKeys::TAB, WebDriverKeys::TAB);
        self::assertSame('#recherche', $this->activeAttribute($client, 'getAttribute("href")'));

        $this->press($client, WebDriverKeys::ENTER);
        self::assertTrue($client->executeScript('return document.getElementById("recherche").open;'));
        self::assertSame('recherche-q', $this->activeAttribute($client, 'id'));
    }

    public function testMainNavigationWithKeyboard(): void
    {
        $client = $this->openAt('/fr', 1440);
        $this->tabTo($client, 'nav-1-bouton');

        $this->press($client, WebDriverKeys::ENTER);
        self::assertSame('true', $this->attributeOf($client, 'nav-1-bouton', 'aria-expanded'));
        self::assertTrue($this->isVisible($client, '#nav-1-panneau'));

        $this->press($client, WebDriverKeys::ARROW_DOWN);
        self::assertSame('/fr/le-ministere', $this->activeAttribute($client, 'getAttribute("href")'));
        $this->press($client, WebDriverKeys::ARROW_DOWN);
        self::assertSame('/fr/le-ministere/missions', $this->activeAttribute($client, 'getAttribute("href")'));
        $this->press($client, WebDriverKeys::ARROW_UP, WebDriverKeys::ARROW_UP);
        self::assertSame('/fr/le-ministere/organisation', $this->activeAttribute($client, 'getAttribute("href")'), 'Boucle vers le dernier lien');
        $this->press($client, WebDriverKeys::HOME);
        self::assertSame('/fr/le-ministere', $this->activeAttribute($client, 'getAttribute("href")'));

        $this->press($client, WebDriverKeys::ESCAPE);
        self::assertSame('nav-1-bouton', $this->activeAttribute($client, 'id'), 'Échap rend le focus au bouton');
        self::assertSame('false', $this->attributeOf($client, 'nav-1-bouton', 'aria-expanded'));
        self::assertFalse($this->isVisible($client, '#nav-1-panneau'));

        $this->press($client, WebDriverKeys::ARROW_RIGHT);
        self::assertSame('nav-2-bouton', $this->activeAttribute($client, 'id'));
        $this->press($client, WebDriverKeys::ARROW_UP);
        self::assertSame('true', $this->attributeOf($client, 'nav-2-bouton', 'aria-expanded'));
        self::assertSame('/fr/demarches?public=citoyens', $this->activeAttribute($client, 'getAttribute("href")'), '↑ ouvre sur le dernier lien');

        $this->press($client, WebDriverKeys::ARROW_RIGHT);
        self::assertSame('nav-3-bouton', $this->activeAttribute($client, 'id'), '→ depuis un sous-menu passe à la rubrique suivante');
        self::assertSame('false', $this->attributeOf($client, 'nav-2-bouton', 'aria-expanded'));

        $this->press($client, WebDriverKeys::ARROW_LEFT, WebDriverKeys::ARROW_LEFT, WebDriverKeys::ARROW_LEFT);
        self::assertSame('/fr/donnees', $this->activeAttribute($client, 'getAttribute("href")'), '← boucle vers la dernière rubrique');

        $this->press($client, WebDriverKeys::HOME, WebDriverKeys::SPACE);
        self::assertSame('true', $this->attributeOf($client, 'nav-1-bouton', 'aria-expanded'), 'Espace ouvre le sous-menu');
        $this->press($client, WebDriverKeys::SPACE);
        self::assertSame('false', $this->attributeOf($client, 'nav-1-bouton', 'aria-expanded'), 'Espace referme le sous-menu');

        $this->press($client, WebDriverKeys::ENTER, WebDriverKeys::TAB);
        self::assertSame('/fr/le-ministere', $this->activeAttribute($client, 'getAttribute("href")'), 'Tab entre dans le sous-menu ouvert');
        $this->tabTo($client, 'nav-2-bouton');
        self::assertFalse($this->isVisible($client, '#nav-1-panneau'), 'Le sous-menu se ferme quand le focus le quitte');
    }

    public function testSearchDialogWithKeyboard(): void
    {
        $client = $this->openAt('/fr', 1440);
        $this->tabTo($client, 'nav-1-bouton');

        $this->chord($client, WebDriverKeys::CONTROL, 'k');
        self::assertTrue($client->executeScript('return document.getElementById("recherche").open;'), 'Ctrl+K ouvre la recherche');
        self::assertSame('recherche-q', $this->activeAttribute($client, 'id'));

        $this->press($client, WebDriverKeys::ESCAPE);
        self::assertFalse($client->executeScript('return document.getElementById("recherche").open;'), 'Échap ferme la recherche');
        self::assertSame('nav-1-bouton', $this->activeAttribute($client, 'id'), 'Le focus revient à son point de départ');

        $this->chord($client, WebDriverKeys::CONTROL, 'k');
        self::assertTrue($client->executeScript('return document.getElementById("recherche").open;'), 'Ctrl+K rouvre la recherche');
        self::assertSame('recherche-q', $this->activeAttribute($client, 'id'));
        $this->press($client, 'presse', WebDriverKeys::ENTER);
        $client->waitForElementToContain('main', 'Résultats pour « presse »');
        self::assertStringEndsWith('/fr/recherche?q=presse', $client->getCurrentURL());
    }

    public function testMobileMenuWithKeyboard(): void
    {
        $client = $this->openAt('/fr', 360);
        $this->tabTo($client, 'menu-mobile', 'getAttribute("aria-controls")');

        $this->press($client, WebDriverKeys::ENTER);
        self::assertTrue($client->executeScript('return document.getElementById("menu-mobile").open;'));
        self::assertSame('true', $client->executeScript('return document.querySelector("[data-mobile-menu-target=trigger]").getAttribute("aria-expanded");'));

        $rect = $client->executeScript('const r = document.getElementById("menu-mobile").getBoundingClientRect(); return [r.width, r.height];');
        self::assertSame([360, 800], $rect, 'Plein écran');

        // Le focus reste dans la modale (ou passe brièvement par l'interface du navigateur, comportement natif de <dialog>).
        for ($i = 0; $i < 25; ++$i) {
            $this->press($client, WebDriverKeys::TAB);
            self::assertTrue($client->executeScript(
                'const a = document.activeElement; return a === document.body || document.getElementById("menu-mobile").contains(a);'
            ), 'Le focus ne sort pas du menu mobile');
        }

        $client->executeScript('document.querySelector("#menu-mobile [aria-controls=menu-mobile-1]").focus();');
        $this->press($client, WebDriverKeys::ENTER);
        self::assertTrue($this->isVisible($client, '#menu-mobile-1'), 'Accordéon ouvert');
        $this->press($client, WebDriverKeys::TAB);
        self::assertSame('/fr/le-ministere', $this->activeAttribute($client, 'getAttribute("href")'));

        $this->press($client, WebDriverKeys::ESCAPE);
        self::assertFalse($client->executeScript('return document.getElementById("menu-mobile").open;'));
        self::assertSame('menu-mobile', $this->activeAttribute($client, 'getAttribute("aria-controls")'), 'Focus rendu au bouton Menu');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function keyboardWidths(): iterable
    {
        yield 'mobile 360 px' => [360];
        yield 'desktop 1440 px' => [1440];
    }

    /**
     * Chaque élément interactif visible est atteint par Tab, avec un indicateur de focus visible.
     */
    #[DataProvider('keyboardWidths')]
    public function testEveryInteractiveElementIsReachableWithVisibleFocus(int $width): void
    {
        $client = $this->openAt('/fr', $width);

        $expected = $client->executeScript(<<<'JS'
            const selector = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';
            let index = 0;
            for (const el of document.querySelectorAll(selector)) {
                if (el.closest('dialog:not([open]), [inert]') || el.getClientRects().length === 0) continue;
                if (getComputedStyle(el).visibility === 'hidden') continue;
                el.dataset.focusIndex = String(index++);
            }
            return index;
            JS);
        self::assertGreaterThan(20, $expected);

        $visited = [];
        for ($i = 0; $i < $expected + 5; ++$i) {
            $this->press($client, WebDriverKeys::TAB);
            // [index de l'élément, problème d'indicateur de focus ('' si conforme)], ou null hors liste.
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

        self::assertCount($expected, $visited, 'Tous les éléments interactifs sont atteignables au clavier');
    }

    // ---- Outils -------------------------------------------------------------

    private function screenshot(Client $client, string $name): void
    {
        if (!is_dir(self::SCREENSHOT_DIR)) {
            mkdir(self::SCREENSHOT_DIR, 0o777, true);
        }
        $client->takeScreenshot(\sprintf('%s/%s.png', self::SCREENSHOT_DIR, $name));
    }

    /**
     * Appuis clavier via l'API W3C Actions : les touches vont à l'élément qui a le focus, comme avec un vrai clavier.
     */
    private function press(Client $client, string ...$keys): void
    {
        $actions = [];
        foreach ($keys as $key) {
            foreach (mb_str_split($key) as $char) {
                $actions[] = ['type' => 'keyDown', 'value' => $char];
                $actions[] = ['type' => 'keyUp', 'value' => $char];
            }
        }

        $this->performKeyActions($client, $actions);
    }

    private function chord(Client $client, string $modifier, string $key): void
    {
        $this->performKeyActions($client, [
            ['type' => 'keyDown', 'value' => $modifier],
            ['type' => 'keyDown', 'value' => $key],
            ['type' => 'keyUp', 'value' => $key],
            ['type' => 'keyUp', 'value' => $modifier],
        ]);
    }

    /**
     * @param list<array{type: string, value: string}> $actions
     */
    private function performKeyActions(Client $client, array $actions): void
    {
        $this->webDriver($client)->executeCustomCommand('/session/:sessionId/actions', 'POST', [
            'actions' => [['type' => 'key', 'id' => 'clavier', 'actions' => $actions]],
        ]);
    }

    private function tabTo(Client $client, string $expected, string $property = 'id', int $max = 40): void
    {
        for ($i = 0; $i < $max; ++$i) {
            $this->press($client, WebDriverKeys::TAB);
            if ($expected === $this->activeAttribute($client, $property)) {
                return;
            }
        }

        self::fail(\sprintf('« %s » non atteint après %d tabulations', $expected, $max));
    }

    private function activeAttribute(Client $client, string $property): mixed
    {
        return $client->executeScript(\sprintf('return document.activeElement.%s;', $property));
    }

    private function attributeOf(Client $client, string $id, string $attribute): mixed
    {
        return $client->executeScript(\sprintf('return document.getElementById(%s).getAttribute(%s);', json_encode($id), json_encode($attribute)));
    }

    private function isVisible(Client $client, string $selector): bool
    {
        return (bool) $client->executeScript(\sprintf(
            'const el = document.querySelector(%s); return !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== "hidden";',
            json_encode($selector),
        ));
    }

    /**
     * Lance axe-core (WCAG 2.0 à 2.2, niveaux A et AA) et renvoie le rapport des violations ('' si aucune).
     */
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
