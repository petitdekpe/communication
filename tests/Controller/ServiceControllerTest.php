<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\Audience;
use App\Enum\PublicationStatus;
use App\Factory\OfficialDocumentFactory;
use App\Factory\ServiceFactory;
use App\ServiceRequest\Accreditation\AccreditationForm;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Liste filtrable par public et fiche d'une démarche (récapitulatif, étapes, pièces requises, base légale).
 */
final class ServiceControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testListIsFilterableByAudience(): void
    {
        $client = static::createClient();
        ServiceFactory::createOne(['audience' => Audience::Journalistes]);
        ServiceFactory::createOne(['audience' => Audience::Medias]);
        ServiceFactory::createOne(['audience' => Audience::Medias]);
        ServiceFactory::new()->create(['audience' => Audience::Citoyens, 'status' => PublicationStatus::Draft]);

        $client->request('GET', '/fr/demarches');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, 'main article', 'Les brouillons ne sont pas publics');
        self::assertSelectorExists('nav[aria-label="Filtrer les démarches par public"] a[aria-current="true"]');

        $client->request('GET', '/fr/demarches?public=medias');
        self::assertSelectorCount(2, 'main article');
        self::assertSelectorTextContains('[role="status"]', '2 démarches');

        $client->request('GET', '/fr/demarches?public=inconnu');
        self::assertSelectorCount(3, 'main article', 'Un public inconnu est ignoré');
    }

    public function testShowPageHasSummaryNumberedStepsAndPrintableChecklist(): void
    {
        $client = static::createClient();
        $legal = OfficialDocumentFactory::createOne();
        $service = ServiceFactory::createOne(['legalBasis' => $legal]);
        $translation = $service->translate('fr');

        $client->request('GET', '/fr/demarches/'.$translation->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', (string) $translation->getTitle());
        self::assertSelectorExists('aside[aria-labelledby="recapitulatif"]');
        self::assertSelectorTextContains('aside', '[À VALIDER]');
        self::assertSelectorCount(3, 'section[aria-labelledby="etapes"] ol li', 'Une ligne par étape');
        self::assertSelectorCount(2, 'section[aria-labelledby="pieces-requises"] input[type="checkbox"]');
        self::assertSelectorCount(2, 'section[aria-labelledby="pieces-requises"] label[for^="piece-"]', 'Chaque case a son libellé');
        self::assertSelectorExists('section[aria-labelledby="base-legale"]');
    }

    public function testInternalFormButtonOnlyForOnlineServicesWithAForm(): void
    {
        $client = static::createClient();
        $withForm = ServiceFactory::createOne(['online' => true, 'formKey' => AccreditationForm::KEY]);
        $without = ServiceFactory::createOne(['online' => false, 'formKey' => AccreditationForm::KEY]);

        $client->request('GET', '/fr/demarches/'.$withForm->translate('fr')->getSlug());
        self::assertSelectorExists('a[href$="/demande"]');

        $client->request('GET', '/fr/demarches/'.$without->translate('fr')->getSlug());
        self::assertSelectorNotExists('a[href$="/demande"]');
    }

    public function testEnglishSiteUsesTranslatedPathAndSlugAndFallsBackToFrench(): void
    {
        $client = static::createClient();
        $service = ServiceFactory::createOne();
        $french = $service->translate('fr')->getSlug();
        $english = $service->translate('en')->getSlug();

        $client->request('GET', '/en/services/'.$english);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[role="note"]');

        $client->request('GET', '/en/services/'.$french);
        self::assertResponseRedirects('/en/services/'.$english, 301);

        $frenchOnly = ServiceFactory::new()->frenchOnly()->create();
        $client->request('GET', '/en/services/'.$frenchOnly->translate('fr')->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="note"]');
    }

    public function testUnpublishedServiceIsNotFound(): void
    {
        $client = static::createClient();
        $draft = ServiceFactory::new()->create(['status' => PublicationStatus::Draft]);

        $client->request('GET', '/fr/demarches/'.$draft->translate('fr')->getSlug());

        self::assertResponseStatusCodeSame(404);
    }
}
