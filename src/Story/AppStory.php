<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\MediaGalleryItem;
use App\Enum\ArticleType;
use App\Enum\Audience;
use App\Enum\DocumentType;
use App\Enum\EventType;
use App\Factory\ArticleFactory;
use App\Factory\CategoryFactory;
use App\Factory\ConsultationFactory;
use App\Factory\EventFactory;
use App\Factory\KeyFigureFactory;
use App\Factory\MediaFactory;
use App\Factory\MediaGalleryFactory;
use App\Factory\MenuItemFactory;
use App\Factory\OfficialDocumentFactory;
use App\Factory\PageFactory;
use App\Factory\ServiceFactory;
use App\Factory\SettingFactory;
use App\Factory\TagFactory;
use App\Factory\VideoFactory;
use App\Home\HomeSettings;
use App\ServiceRequest\Accreditation\AccreditationForm;
use Zenstruck\Foundry\Story;

/**
 * Jeu de données de démonstration : uniquement des textes « [À VALIDER] ».
 * Un article sur trois n'existe qu'en français (démonstration du repli sur /en).
 */
final class AppStory extends Story
{
    public function build(): void
    {
        $images = MediaFactory::new()->image()->many(3)->create();
        $categories = CategoryFactory::createMany(4, static fn (int $i): array => ['position' => $i]);
        $tags = TagFactory::createMany(6);

        $gallery = MediaGalleryFactory::createOne([
            'items' => array_map(static fn (int $i): MediaGalleryItem => new MediaGalleryItem($images[$i], $i), array_keys($images)),
        ]);

        $types = ArticleType::cases();
        for ($i = 0; $i < 12; ++$i) {
            $type = $types[$i % \count($types)];
            $factory = ArticleFactory::new()->ofType($type);
            if (0 === $i % 3) {
                $factory = $factory->frenchOnly();
            }
            $factory->create([
                'category' => $categories[$i % \count($categories)],
                'tags' => [$tags[$i % \count($tags)], $tags[($i + 1) % \count($tags)]],
                'coverImage' => $images[$i % \count($images)],
                // L'article n° 2 (un dossier, traduit en anglais) est à la une, avec galerie et pièce jointe.
                'featured' => 2 === $i,
                'attachments' => ArticleType::Communique === $type || 2 === $i ? [OfficialDocumentFactory::createOne()] : [],
                'gallery' => 2 === $i ? $gallery : null,
            ]);
        }
        ArticleFactory::new()->draft()->create();

        // Bibliothèque : tous les types, dates étalées, un fichier en anglais.
        $documentTypes = DocumentType::cases();
        OfficialDocumentFactory::createMany(12, static fn (int $i): array => [
            'category' => $categories[$i % \count($categories)],
            'type' => $documentTypes[$i % \count($documentTypes)],
            'documentDate' => new \DateTimeImmutable(\sprintf('-%d days', 20 + $i * 37)),
            'fileLanguage' => 4 === $i % 5 ? 'en' : 'fr',
        ]);

        // Agenda : événements à venir (dont un sur plusieurs jours) et passés, de plusieurs types.
        $eventTypes = EventType::cases();
        EventFactory::createMany(6, static fn (int $i): array => [
            'type' => $eventTypes[$i % \count($eventTypes)],
            'startsAt' => new \DateTimeImmutable(\sprintf('+%d days 09:00', 3 + $i * 9)),
            'endsAt' => 2 === $i ? new \DateTimeImmutable(\sprintf('+%d days 17:00', 5 + $i * 9)) : null,
        ]);
        EventFactory::createMany(3, static fn (int $i): array => [
            'type' => $eventTypes[$i % \count($eventTypes)],
            'startsAt' => new \DateTimeImmutable(\sprintf('-%d days 10:00', 10 + $i * 20)),
        ]);

        foreach (Audience::cases() as $audience) {
            ServiceFactory::new()->featured()->create(['audience' => $audience, 'position' => 1]);
            ServiceFactory::createOne(['audience' => $audience, 'position' => 2]);
        }
        ServiceFactory::new()->featured()->create(['audience' => Audience::Journalistes, 'position' => 3]);

        ServiceFactory::createOne([
            'audience' => Audience::Journalistes,
            'online' => true,
            'formKey' => AccreditationForm::KEY,
            'legalBasis' => OfficialDocumentFactory::createOne(),
            'position' => 0,
        ]);

        KeyFigureFactory::createMany(4, static fn (int $i): array => ['position' => $i]);

        // Consultation ouverte (avec un document) et consultation close dont les résultats sont publiés.
        ConsultationFactory::createOne(['documents' => [OfficialDocumentFactory::createOne()]]);
        ConsultationFactory::createOne([
            'opensAt' => new \DateTimeImmutable('-60 days'),
            'closesAt' => new \DateTimeImmutable('-10 days'),
            'resultsPublished' => true,
        ]);

        // Médiathèque : une galerie de photos raster (vignettes) et deux vidéos.
        $photos = MediaFactory::new()->photo()->many(3)->create();
        MediaGalleryFactory::createOne([
            'items' => array_map(static fn (int $i): MediaGalleryItem => new MediaGalleryItem($photos[$i], $i), array_keys($photos)),
        ]);
        VideoFactory::createMany(2, static fn (int $i): array => ['position' => $i]);

        $parent = PageFactory::createOne();
        PageFactory::createMany(2, ['parent' => $parent]);

        foreach (['main' => 7, 'utility' => 3, 'legal' => 3] as $menu => $count) {
            MenuItemFactory::createMany($count, static fn (int $i): array => ['menu' => $menu, 'position' => $i]);
        }

        foreach (['contact.address', 'contact.phone', 'contact.email', 'helpline.phone', HomeSettings::NEWSLETTER_CONSENT, HomeSettings::NOTICE_MESSAGE, HomeSettings::HERO_TITLE, HomeSettings::HERO_LEAD, HomeSettings::HERO_IMAGE_ALT, HomeSettings::NEWSLETTER_TEXT] as $name) {
            SettingFactory::createOne(['name' => $name]);
        }
        // Valeurs techniques (pas des textes) : chemin d'une image du dossier assets/ et lien de l'avis.
        SettingFactory::createOne(['name' => HomeSettings::HERO_IMAGE, 'values' => ['fr' => 'images/demo/photo-4x3.svg', 'en' => 'images/demo/photo-4x3.svg']]);
        SettingFactory::createOne(['name' => HomeSettings::NOTICE_LINK, 'values' => ['fr' => '/fr/communiques', 'en' => '/en/press-releases']]);
    }
}
