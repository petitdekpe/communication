<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Article;
use App\Entity\AuditLog;
use App\Entity\OfficialDocument;
use App\Enum\AdminRole;
use App\Enum\ArticleType;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Factory\OfficialDocumentFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Formulaires de contenu (onglets FR / EN), rôle traducteur (anglais seulement), listes (tri, filtres, pagination,
 * recherche) et documents avec fichier.
 */
final class AdminContentTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    public function testTranslatorOnlyEditsTheEnglishFieldsEvenWithATamperedRequest(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Translator);
        $article = ArticleFactory::createOne(['type' => ArticleType::Actualite, 'featured' => false]);
        $id = (int) $article->getId();
        $frTitle = (string) $article->translate('fr')->getTitle();

        $crawler = $client->request('GET', '/admin/articles/'.$id);
        self::assertResponseIsSuccessful();
        // Onglet anglais actif par défaut ; champs français et généraux désactivés.
        self::assertSelectorExists('[role="tab"][id="tab-en"][aria-selected="true"]');
        self::assertSelectorExists('#article_admin_translations_fr_title[disabled]');
        self::assertSelectorExists('#article_admin_type[disabled]');
        self::assertSelectorNotExists('#article_admin_translations_en_title[disabled]');

        // Requête falsifiée : le français, le type et « à la une » sont envoyés, mais ne sont pas pris en compte.
        $client->request('POST', '/admin/articles/'.$id, ['article_admin' => [
            'type' => 'communique',
            'featured' => '1',
            'translations' => [
                'fr' => ['title' => 'Titre piraté', 'excerpt' => '', 'body' => ''],
                'en' => ['title' => 'English title', 'excerpt' => 'Lead', 'body' => '<p>Body</p>'],
            ],
            '_token' => $this->token($crawler, 'input[name="article_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);

        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $saved = $manager->find(Article::class, $id);
        self::assertSame('English title', $saved->translate('en')->getTitle());
        self::assertSame($frTitle, $saved->translate('fr')->getTitle(), 'Le français est intact');
        self::assertSame(ArticleType::Actualite, $saved->getType());
        self::assertFalse($saved->isFeatured());
    }

    public function testTranslatorCannotCreateNorOpenArchivedContent(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Translator);
        $archived = ArticleFactory::createOne(['status' => PublicationStatus::Archived]);

        $client->request('GET', '/admin/articles/nouveau');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin/articles/'.$archived->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorCanEditDraftsButNotPublishedContent(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Editor);
        $draft = ArticleFactory::createOne(['status' => PublicationStatus::Draft]);
        $published = ArticleFactory::createOne(['status' => PublicationStatus::Published]);

        $client->request('GET', '/admin/articles/'.$draft->getId());
        self::assertResponseIsSuccessful();
        $client->request('GET', '/admin/articles/'.$published->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testFrenchTitleIsRequiredAndErrorsLinkToTheField(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Editor);
        $crawler = $client->request('GET', '/admin/articles/nouveau');

        $client->request('POST', '/admin/articles/nouveau', ['article_admin' => [
            'type' => 'actualite',
            'translations' => ['fr' => ['title' => '', 'excerpt' => '', 'body' => ''], 'en' => ['title' => '', 'excerpt' => 'Seulement un chapô', 'body' => '']],
            '_token' => $this->token($crawler, 'input[name="article_admin[_token]"]'),
        ]], [], $this->origin());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"] a[href="#article_admin_translations_fr_title"]');
        self::assertSelectorExists('[role="alert"] a[href="#article_admin_translations_en_title"]', 'Titre anglais exigé dès qu\'un autre champ anglais est rempli');
        self::assertSame([], static::getContainer()->get('doctrine')->getManager()->getRepository(Article::class)->findAll());
    }

    public function testLocaleTabsShowCompletenessAndFollowTheWorkingLanguage(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        $article = ArticleFactory::new()->frenchOnly()->create();

        $crawler = $client->request('GET', '/admin/articles/'.$article->getId());
        self::assertSelectorCount(2, '[role="tablist"] [role="tab"]');
        self::assertSelectorTextContains('#tab-fr', '3/3');
        self::assertSelectorTextContains('#tab-en', '0/3');
        self::assertSelectorExists('#tab-fr[aria-selected="true"][tabindex="0"]');
        self::assertSelectorExists('#tab-en[tabindex="-1"]');
        self::assertSelectorExists('#panel-en[hidden][role="tabpanel"][aria-labelledby="tab-en"]');

        // Bascule de la langue de travail : l'onglet anglais s'ouvre par défaut.
        $client->request('POST', '/admin/langue', ['_token' => $this->token($crawler, 'form[action$="/admin/langue"] input[name="_token"]'), 'locale' => 'en', 'redirect' => '/admin/articles/'.$article->getId()], [], $this->origin());
        self::assertResponseRedirects('/admin/articles/'.$article->getId());
        $client->followRedirect();
        self::assertSelectorExists('#tab-en[aria-selected="true"]');
        self::assertSelectorExists('button[name="locale"][value="en"][aria-pressed="true"]');
    }

    public function testLanguageSwitchRefusesExternalRedirectsAndBadTokens(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        $crawler = $client->request('GET', '/admin');
        $token = $this->token($crawler, 'form[action$="/admin/langue"] input[name="_token"]');

        foreach (['https://evil.example/', '//evil.example', '/fr/actualites', '/admin\\evil'] as $target) {
            $client->request('POST', '/admin/langue', ['_token' => $token, 'locale' => 'fr', 'redirect' => $target], [], $this->origin());
            $location = (string) $client->getResponse()->headers->get('Location');
            self::assertStringNotContainsString('evil', $location, $target);
            self::assertStringNotContainsString('/fr/actualites', $location, $target);
        }

        $client->request('POST', '/admin/langue', ['_token' => 'faux', 'locale' => 'fr'], [], $this->origin());
        self::assertResponseStatusCodeSame(403);
    }

    public function testListSortingFilteringSearchAndPagination(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        ArticleFactory::createMany(23, ['status' => PublicationStatus::Draft]);
        ArticleFactory::createMany(2, ['status' => PublicationStatus::Archived]);
        $special = ArticleFactory::createOne(['status' => PublicationStatus::Published]);
        $special->translate('fr')->setTitle('Zorglub spécial');
        static::getContainer()->get('doctrine')->getManager()->flush();

        // 26 contenus : 20 par page, deux pages, aria-current sur la page courante.
        $client->request('GET', '/admin/liste/articles');
        self::assertSelectorCount(20, 'tbody tr');
        self::assertSelectorExists('nav[aria-label="Pagination"] a[aria-current="page"]');
        $client->request('GET', '/admin/liste/articles?page=2');
        self::assertSelectorCount(6, 'tbody tr');
        $client->request('GET', '/admin/liste/articles?page=99');
        self::assertResponseIsSuccessful('Une page hors limites retombe sur la dernière page');
        self::assertSelectorCount(6, 'tbody tr');

        // Filtre de statut et recherche, conservés dans l'URL.
        $client->request('GET', '/admin/liste/articles?status=archived');
        self::assertSelectorCount(2, 'tbody tr');
        self::assertSelectorExists('#filtre-status option[value="archived"][selected]');
        $client->request('GET', '/admin/liste/articles?q=zorglub');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody tr', 'Zorglub spécial');
        $client->request('GET', '/admin/liste/articles?q='.rawurlencode('%'));
        self::assertSelectorNotExists('tbody tr th', 'Le joker « % » est cherché littéralement');

        // Tri par statut : indiqué par aria-sort ; une colonne inconnue est ignorée.
        $client->request('GET', '/admin/liste/articles?sort=status&dir=asc');
        self::assertSelectorExists('th[aria-sort="ascending"]');
        $client->request('GET', '/admin/liste/articles?sort=id);DROP&dir=asc');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('th[aria-sort="ascending"]');
    }

    public function testTableMarkupIsAccessible(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Reviewer);
        ArticleFactory::createMany(2);

        $client->request('GET', '/admin/liste/articles');

        self::assertSelectorExists('table caption.sr-only');
        self::assertSelectorExists('thead th[scope="col"]');
        self::assertSelectorExists('tbody tr th[scope="row"]');
        self::assertSelectorExists('input[type="checkbox"][aria-label][data-table-select-target="all"]');
        self::assertSelectorExists('input[type="checkbox"][name="ids[]"][aria-label]');
        self::assertSelectorExists('form[role="search"][aria-label]');
        self::assertSelectorExists('#toasts[role="status"][aria-live="polite"]');
    }

    public function testDocumentCreationWithFileAndTwoLanguages(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Editor);
        $crawler = $client->request('GET', '/admin/documents/nouveau');
        self::assertSelectorExists('input[type="file"]');

        $pdf = $this->tempPdf();
        $client->request('POST', '/admin/documents/nouveau', ['document_admin' => [
            'type' => 'decree',
            'reference' => 'D-2026-1',
            'fileLanguage' => 'fr',
            'translations' => ['fr' => ['title' => 'Décret de test', 'summary' => 'Résumé'], 'en' => ['title' => 'Test decree', 'summary' => '']],
            '_token' => $this->token($crawler, 'input[name="document_admin[_token]"]'),
        ]], ['document_admin' => ['file' => new UploadedFile($pdf, 'decret.pdf', 'application/pdf', null, true)]], $this->origin());

        self::assertResponseStatusCodeSame(303);
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $documents = $manager->getRepository(OfficialDocument::class)->findAll();
        self::assertCount(1, $documents);
        self::assertSame('D-2026-1', $documents[0]->getReference());
        self::assertSame('application/pdf', $documents[0]->getFile()?->getMimeType());
        self::assertSame('Test decree', $documents[0]->translate('en')->getTitle());
        @unlink($pdf);
    }

    public function testDocumentFileIsRequiredAndItsTypeChecked(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Editor);
        $crawler = $client->request('GET', '/admin/documents/nouveau');
        $base = ['type' => 'law', 'fileLanguage' => 'fr', 'translations' => ['fr' => ['title' => 'Loi', 'summary' => ''], 'en' => ['title' => '', 'summary' => '']], '_token' => $this->token($crawler, 'input[name="document_admin[_token]"]')];

        $client->request('POST', '/admin/documents/nouveau', ['document_admin' => $base], [], $this->origin());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Choisissez un fichier');

        $script = tempnam(sys_get_temp_dir(), 'mcm');
        file_put_contents((string) $script, '<?php echo 1;');
        $client->request('POST', '/admin/documents/nouveau', ['document_admin' => $base], ['document_admin' => ['file' => new UploadedFile((string) $script, 'loi.pdf', 'application/pdf', null, true)]], $this->origin());
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], static::getContainer()->get('doctrine')->getManager()->getRepository(OfficialDocument::class)->findAll());
        @unlink((string) $script);
    }

    public function testAuditTrailRecordsBeforeAndAfterValuesAndIsFilterable(): void
    {
        $client = $this->client();
        $editor = $this->loginAs($client, AdminRole::Editor);
        $document = OfficialDocumentFactory::createOne(['status' => PublicationStatus::Draft, 'reference' => 'AVANT']);
        $id = (int) $document->getId();

        $crawler = $client->request('GET', '/admin/documents/'.$id);
        $client->request('POST', '/admin/documents/'.$id, ['document_admin' => [
            'type' => $document->getType()->value,
            'reference' => 'APRES',
            'fileLanguage' => 'fr',
            'translations' => ['fr' => ['title' => 'Nouveau titre', 'summary' => ''], 'en' => ['title' => '', 'summary' => '']],
            '_token' => $this->token($crawler, 'input[name="document_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);

        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $logs = $manager->getRepository(AuditLog::class)->findBy(['entityClass' => 'OfficialDocument', 'entityId' => $id, 'action' => 'update']);
        self::assertCount(1, $logs);
        self::assertSame($editor->getUserIdentifier(), $logs[0]->getActor());
        self::assertSame(['AVANT', 'APRES'], $logs[0]->getChanges()['reference']);
        self::assertSame('Nouveau titre', $logs[0]->getChanges()['fr.title'][1]);

        // Consultation filtrée (administrateur).
        self::ensureKernelShutdown();
        $admin = $this->client();
        $this->loginAs($admin, AdminRole::Admin);
        $admin->request('GET', '/admin/audit?actor='.rawurlencode($editor->getUserIdentifier()).'&action=update');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'APRES');
        self::assertSelectorTextContains('tbody', 'reference');
        $admin->request('GET', '/admin/audit?actor=personne%40example.org');
        self::assertSelectorNotExists('tbody tr');
    }

    private function tempPdf(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'mcm');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return $path;
    }
}
