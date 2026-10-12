<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Entity\MediaGalleryItem;
use App\Factory\MediaFactory;
use App\Factory\MediaGalleryFactory;
use App\Factory\VideoFactory;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Médiathèque dans un vrai Chrome : les vignettes LiipImagine se chargent, la visionneuse se pilote au clavier
 * (flèches, Échap, focus rendu), et la vidéo n'est demandée à youtube-nocookie.com qu'après le clic.
 */
#[ResetDatabase]
final class MediaLibraryJourneyTest extends PantherTestCase
{
    use ViewportTrait;

    public function testThumbnailsViewerAndLazyVideo(): void
    {
        self::bootKernel();
        $photos = MediaFactory::new()->photo()->many(3)->create();
        MediaGalleryFactory::createOne([
            'items' => array_map(static fn (int $i): MediaGalleryItem => new MediaGalleryItem($photos[$i], $i), array_keys($photos)),
        ]);
        VideoFactory::createOne(['youtubeId' => 'AbC-_123456']);
        self::ensureKernelShutdown();

        $client = $this->openAt('/fr/mediatheque', 1280);
        $client->waitFor('button[data-action="lightbox#open"] img');

        // Vignettes : chargées (largeur naturelle > 0) et redimensionnées à 480 px.
        $client->wait()->until(static fn () => true === $client->executeScript(
            'return Array.from(document.querySelectorAll("button[data-action=\'lightbox#open\'] img")).every(i => i.complete && i.naturalWidth > 0);'
        ));
        self::assertSame(480, $client->executeScript('return document.querySelector("button[data-action=\'lightbox#open\'] img").naturalWidth;'));

        // Aucune requête vers YouTube avant le clic.
        self::assertFalse($client->executeScript('return !!document.querySelector("iframe");'));

        // Visionneuse : ouverture, focus dans la boîte, navigation aux flèches, Échap, focus rendu à la vignette.
        $first = $client->findElement(WebDriverBy::cssSelector('button[data-action="lightbox#open"]'));
        $first->click();
        $client->waitFor('dialog[open]');
        self::assertTrue($client->executeScript('return document.querySelector("dialog[open]").contains(document.activeElement);'));
        self::assertSame('Image 1 sur 3', trim($client->findElement(WebDriverBy::cssSelector('dialog[open] [role="status"]'))->getText()));

        $client->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
        self::assertSame('Image 2 sur 3', trim($client->findElement(WebDriverBy::cssSelector('dialog[open] [role="status"]'))->getText()));
        $client->getKeyboard()->sendKeys(WebDriverKeys::ARROW_LEFT);
        $client->getKeyboard()->sendKeys(WebDriverKeys::ARROW_LEFT);
        self::assertSame('Image 3 sur 3', trim($client->findElement(WebDriverBy::cssSelector('dialog[open] [role="status"]'))->getText()), 'Navigation circulaire');

        $client->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
        $client->wait()->until(static fn () => false === $client->executeScript('return !!document.querySelector("dialog[open]");'));
        self::assertTrue($client->executeScript('return document.activeElement.matches("button[data-action=\'lightbox#open\']");'), 'Le focus revient à la vignette');

        // Vidéo : au clic, un iframe youtube-nocookie.com remplace le bouton.
        $client->findElement(WebDriverBy::cssSelector('button[data-action="video-embed#play"]'))->click();
        $client->waitFor('iframe');
        $src = (string) $client->executeScript('return document.querySelector("iframe").src;');
        self::assertStringStartsWith('https://www.youtube-nocookie.com/embed/AbC-_123456', $src);
        self::assertNotSame('', (string) $client->executeScript('return document.querySelector("iframe").title;'), 'iframe titré');
    }
}
