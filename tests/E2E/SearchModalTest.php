<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Factory\PageFactory;
use App\Factory\ServiceFactory;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * La modale Ctrl/Cmd+K du header mène à la page de recherche globale, résultats groupés par type, en FR et en EN.
 */
#[ResetDatabase]
final class SearchModalTest extends PantherTestCase
{
    use ViewportTrait;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', 'recherche'];
        yield 'en' => ['en', 'search'];
    }

    #[DataProvider('locales')]
    public function testCtrlKOpensTheDialogAndSubmittingShowsGroupedResults(string $locale, string $path): void
    {
        self::bootKernel();
        foreach ([PageFactory::createOne(), ServiceFactory::createOne()] as $content) {
            foreach (['fr', 'en'] as $l) {
                $content->translate($l)->setTitle('Zorglub '.$content::class);
            }
        }
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::ensureKernelShutdown();

        $client = $this->openAt('/'.$locale, 1280);

        $this->webDriver($client)->executeCustomCommand('/session/:sessionId/actions', 'POST', [
            'actions' => [['type' => 'key', 'id' => 'clavier', 'actions' => [
                ['type' => 'keyDown', 'value' => WebDriverKeys::CONTROL],
                ['type' => 'keyDown', 'value' => 'k'],
                ['type' => 'keyUp', 'value' => 'k'],
                ['type' => 'keyUp', 'value' => WebDriverKeys::CONTROL],
            ]]],
        ]);
        $client->waitFor('dialog#recherche[open]');
        self::assertSame('recherche-q', $client->executeScript('return document.activeElement.id;'), 'Le champ de recherche a le focus');

        $client->findElement(WebDriverBy::id('recherche-q'))->sendKeys('zorglub'.WebDriverKeys::ENTER);

        $client->waitFor('section[data-search-group]');
        self::assertStringContainsString(\sprintf('/%s/%s?q=zorglub', $locale, $path), $client->getCurrentURL());
        self::assertCount(2, $client->getCrawler()->filter('section[data-search-group]'), 'Un groupe par type de résultat');
        self::assertSame(
            ['service', 'page'],
            $client->getCrawler()->filter('section[data-search-group]')->each(static fn ($s): string => (string) $s->attr('data-search-group')),
        );
    }
}
