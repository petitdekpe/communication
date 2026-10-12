<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Story\AppStory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;
use Zenstruck\Foundry\Attribute\WithStory;

/**
 * Captures pleine page de l'accueil pour la démo : var/screenshots/accueil-{fr,en}-{desktop,mobile}.png.
 * Lancer seul : make screenshots.
 */
#[ResetDatabase]
#[WithStory(AppStory::class)]
final class HomeScreenshotTest extends PantherTestCase
{
    use ViewportTrait;

    private const string DIR = __DIR__.'/../../var/screenshots';

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function captures(): iterable
    {
        foreach (['fr', 'en'] as $locale) {
            yield $locale.' desktop' => [$locale, 'desktop', 1440];
            yield $locale.' mobile' => [$locale, 'mobile', 390];
        }
    }

    #[DataProvider('captures')]
    public function testFullPageScreenshot(string $locale, string $device, int $width): void
    {
        $client = $this->openAt('/'.$locale, $width);
        $this->waitForFonts($client);

        self::assertLessThanOrEqual($width, $client->executeScript('return document.documentElement.scrollWidth;'), 'Pas de débordement horizontal');
        self::assertSame(10, $client->executeScript('return document.querySelectorAll("main > *").length;'), 'Les 10 blocs de la page sont présents');

        // Fenêtre agrandie à la hauteur du document : la capture couvre toute la page.
        $height = $client->executeScript('return Math.ceil(document.documentElement.scrollHeight);');
        self::assertIsInt($height);
        // Les images en chargement différé (loading="lazy") ne se chargent qu'une fois dans la fenêtre.
        $this->setViewport($client, $width, $height);
        $this->waitForImages($client);

        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0o777, true);
        }
        $file = \sprintf('%s/accueil-%s-%s.png', self::DIR, $locale, $device);
        $client->takeScreenshot($file);

        $size = getimagesize($file);
        self::assertIsArray($size);
        self::assertSame($width, $size[0]);
        self::assertGreaterThan(3000, $size[1], 'Capture pleine page');
    }

    private function waitForFonts(Client $client): void
    {
        $client->executeAsyncScript('const done = arguments[arguments.length - 1]; document.fonts.ready.then(() => done(true));');
    }

    private function waitForImages(Client $client): void
    {
        $client->wait(10)->until(static fn ($driver): bool => true === $driver->executeScript(
            'return Array.from(document.images).every((img) => img.complete && img.naturalWidth > 0);'
        ));
    }
}
