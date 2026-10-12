<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MediaGalleryItem;
use App\Enum\PublicationStatus;
use App\Factory\MediaFactory;
use App\Factory\MediaGalleryFactory;
use App\Factory\VideoFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Médiathèque : galeries (vignettes, visionneuse accessible) et vidéos YouTube chargées au clic.
 */
final class MediaLibraryControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', 'mediatheque'];
        yield 'en' => ['en', 'media-library'];
    }

    #[DataProvider('locales')]
    public function testGalleryUsesThumbnailsAndAnAccessibleViewer(string $locale, string $path): void
    {
        $client = static::createClient();
        $photos = MediaFactory::new()->photo()->many(3)->create();
        MediaGalleryFactory::createOne([
            'items' => array_map(static fn (int $i): MediaGalleryItem => new MediaGalleryItem($photos[$i], $i), array_keys($photos)),
        ]);
        MediaGalleryFactory::new()->create(['status' => PublicationStatus::Draft]);

        $crawler = $client->request('GET', \sprintf('/%s/%s', $locale, $path));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'section[data-controller="lightbox"]', 'Les brouillons ne sont pas publics');
        self::assertSelectorCount(3, 'button[data-action="lightbox#open"] img');
        foreach ($crawler->filter('button[data-action="lightbox#open"]') as $node) {
            $button = new Crawler($node);
            $img = $button->filter('img');
            self::assertStringContainsString('/media/cache/resolve/thumb/', (string) $img->attr('src'), 'Vignette LiipImagine');
            self::assertNotSame('', trim((string) $img->attr('alt')), 'Texte alternatif obligatoire');
            self::assertStringContainsString('/media/cache/resolve/viewer/', (string) $button->attr('data-full'));
        }
        // Visionneuse : <dialog> nommé, bouton de fermeture, navigation, compteur annoncé.
        self::assertSelectorExists('dialog[aria-labelledby]');
        self::assertSelectorExists('dialog button[data-action="lightbox#close"] .sr-only');
        self::assertSelectorExists('dialog button[data-action="lightbox#previous"]');
        self::assertSelectorExists('dialog button[data-action="lightbox#next"]');
        self::assertSelectorExists('dialog [aria-live="polite"][role="status"]');
    }

    #[DataProvider('locales')]
    public function testVideosAreNotLoadedFromYouTubeUntilClicked(string $locale, string $path): void
    {
        $client = static::createClient();
        VideoFactory::createOne(['youtubeId' => 'AbC-_123456']);

        $crawler = $client->request('GET', \sprintf('/%s/%s', $locale, $path));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('iframe', 'Aucun iframe avant le clic');
        self::assertSelectorNotExists('[src*="youtube"], [href*="youtube-nocookie"]');
        self::assertSame('AbC-_123456', $crawler->filter('[data-controller="video-embed"]')->attr('data-video-embed-id-value'));
        self::assertSelectorExists('button[data-action="video-embed#play"]');
        // Le contrôleur construit l'adresse youtube-nocookie.com, jamais youtube.com.
        $script = (string) file_get_contents(\dirname(__DIR__, 2).'/assets/controllers/video_embed_controller.js');
        self::assertStringContainsString('https://www.youtube-nocookie.com/embed/', $script);
        self::assertStringNotContainsString('https://www.youtube.com/embed', $script);
    }

    public function testEmptyStatesAreShown(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/mediatheque');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Aucune galerie');
        self::assertSelectorTextContains('main', 'Aucune vidéo');
    }

    public function testVideoIdMustBeElevenSafeCharacters(): void
    {
        $video = VideoFactory::new()->withoutPersisting()->create(['youtubeId' => 'x"><script>']);

        $violations = self::getContainer()->get('validator')->validate($video);

        self::assertGreaterThan(0, \count($violations));
    }
}
