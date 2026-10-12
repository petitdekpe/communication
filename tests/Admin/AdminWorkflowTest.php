<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Article;
use App\Entity\AuditLog;
use App\Enum\AdminRole;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Circuit de publication : l'éditeur crée et soumet, le relecteur valide et publie, l'administrateur supprime.
 * Chaque transition passe par un Voter ; chaque changement est inscrit au journal d'audit.
 */
final class AdminWorkflowTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    public function testEditorCreatesAndSubmitsButCannotPublishNorDelete(): void
    {
        $client = $this->client();
        $editor = $this->loginAs($client, AdminRole::Editor);

        // Création par le formulaire (français obligatoire, anglais facultatif).
        $crawler = $client->request('GET', '/admin/articles/nouveau?type=communique');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/articles/nouveau', ['article_admin' => [
            'type' => 'communique',
            'category' => '',
            'translations' => ['fr' => ['title' => 'Communiqué de test', 'excerpt' => 'Chapô', 'body' => '<p>Texte</p>'], 'en' => ['title' => '', 'excerpt' => '', 'body' => '']],
            '_token' => $this->token($crawler, 'input[name="article_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);

        $article = $this->only(Article::class);
        self::assertSame(PublicationStatus::Draft, $article->getStatus());
        self::assertSame('Communiqué de test', $article->translate('fr')->getTitle());
        self::assertNull($article->getTranslation('en'), 'Une langue vide n\'est pas créée');

        // L'éditeur ne voit que « soumettre » ; publier et supprimer sont refusés même avec un jeton valide.
        $id = (int) $article->getId();
        self::assertSame(['submit'], $this->availableActions($client, $id));
        foreach (['publish', 'delete', 'archive'] as $transition) {
            $this->act($client, $id, $transition, $this->tokenFor($client, $id));
            self::assertResponseStatusCodeSame(403, $transition);
        }

        $this->act($client, $id, 'submit', $this->tokenFor($client, $id));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(PublicationStatus::InReview, $this->only(Article::class)->getStatus());

        // Soumis : l'éditeur ne peut plus modifier le contenu.
        $client->request('GET', '/admin/articles/'.$id);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($editor->getId());
    }

    public function testReviewerPublishesUnpublishesAndRejects(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Reviewer);
        $article = ArticleFactory::createOne(['status' => PublicationStatus::InReview, 'publishedAt' => null]);
        $id = (int) $article->getId();

        // « Refuser » exige un motif : il n'est proposé que dans l'en-tête du formulaire, pas dans la liste.
        self::assertSame(['publish'], $this->availableActions($client, $id));

        $this->act($client, $id, 'publish', $this->tokenFor($client, $id));
        self::assertResponseStatusCodeSame(303);
        $published = $this->only(Article::class);
        self::assertSame(PublicationStatus::Published, $published->getStatus());
        self::assertNotNull($published->getPublishedAt(), 'La date de publication est renseignée à la première publication');

        // Visible sur le site public.
        $client->request('GET', '/fr/actualites/'.$published->translate('fr')->getSlug());
        self::assertResponseIsSuccessful();

        $this->act($client, $id, 'unpublish', $this->tokenFor($client, $id));
        self::assertSame(PublicationStatus::Draft, $this->only(Article::class)->getStatus());
        $client->request('GET', '/fr/actualites/'.$published->translate('fr')->getSlug());
        self::assertResponseStatusCodeSame(404);

        // Le relecteur ne supprime pas : réservé à l'administrateur.
        $this->act($client, $id, 'delete', $this->tokenFor($client, $id));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminDeletesAfterConfirmation(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        $article = ArticleFactory::createOne();
        $id = (int) $article->getId();

        $crawler = $client->request('GET', '/admin/liste/articles');
        self::assertSelectorExists('dialog[aria-labelledby="confirm-title"]', 'Boîte de confirmation native');
        self::assertSelectorExists(\sprintf('button[data-action="confirm-dialog#ask"][data-confirm-dialog-form-param="action-articles-%d-delete"]', $id));
        unset($crawler);

        $this->act($client, $id, 'delete', $this->tokenFor($client, $id));
        self::assertResponseStatusCodeSame(303);
        self::assertNull(static::getContainer()->get('doctrine')->getManager()->getRepository(Article::class)->find($id));
    }

    public function testInvalidCsrfTokenIsRefused(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        $id = (int) ArticleFactory::createOne()->getId();

        $this->act($client, $id, 'publish', 'jeton-invalide');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(PublicationStatus::Published, $this->only(Article::class)->getStatus(), 'Statut inchangé (publié par la fabrique)');
    }

    public function testTokenOfAnotherRowCannotBeReused(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Admin);
        $first = (int) ArticleFactory::createOne()->getId();
        $second = (int) ArticleFactory::createOne()->getId();

        $this->act($client, $second, 'archive', $this->tokenFor($client, $first));

        self::assertResponseStatusCodeSame(403);
    }

    public function testBulkActionAppliesOnlyWhatIsAllowed(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Reviewer);
        $draft = (int) ArticleFactory::createOne(['status' => PublicationStatus::InReview])->getId();
        $archived = (int) ArticleFactory::createOne(['status' => PublicationStatus::Archived])->getId();

        $crawler = $client->request('GET', '/admin/liste/articles');
        self::assertSelectorExists('form[id="bulk-articles"] select[name="transition"]');
        $client->request('POST', '/admin/liste/articles/groupe', [
            '_token' => $this->token($crawler, 'form#bulk-articles input[name="_token"]'),
            'transition' => 'publish',
            'ids' => [$draft, $archived],
        ], [], $this->origin());

        self::assertResponseStatusCodeSame(303);
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        self::assertSame(PublicationStatus::Published, $manager->find(Article::class, $draft)?->getStatus());
        self::assertSame(PublicationStatus::Archived, $manager->find(Article::class, $archived)?->getStatus(), 'Un contenu archivé ne se publie pas directement');
    }

    public function testTurboStreamAnswerReplacesTheRowAndAddsAToast(): void
    {
        $client = $this->client();
        $this->loginAs($client, AdminRole::Reviewer);
        $id = (int) ArticleFactory::createOne(['status' => PublicationStatus::InReview])->getId();

        $this->act($client, $id, 'publish', $this->tokenFor($client, $id), ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<turbo-stream action="replace" target="row-articles-'.$id.'">', $body);
        self::assertStringContainsString('<turbo-stream action="append" target="toasts">', $body);
        self::assertStringContainsString('Publié', $body);
    }

    public function testEveryChangeIsRecordedInTheAuditTrail(): void
    {
        $client = $this->client();
        $reviewer = $this->loginAs($client, AdminRole::Reviewer);
        $id = (int) ArticleFactory::createOne(['status' => PublicationStatus::InReview])->getId();

        $this->act($client, $id, 'publish', $this->tokenFor($client, $id));
        $this->act($client, $id, 'unpublish', $this->tokenFor($client, $id));

        $manager = static::getContainer()->get('doctrine')->getManager();
        $logs = $manager->getRepository(AuditLog::class)->findBy(['entityClass' => 'Article', 'entityId' => $id], ['id' => 'ASC']);
        $actions = array_map(static fn (AuditLog $l): string => $l->getAction(), $logs);

        self::assertContains('create', $actions, 'Création enregistrée');
        self::assertSame(['publish', 'unpublish'], \array_slice($actions, -2));
        $publish = $logs[\count($logs) - 2];
        self::assertSame($reviewer->getUserIdentifier(), $publish->getActor());
        self::assertSame(['in_review', 'published'], $publish->getChanges()['status'], 'Valeurs avant / après');
    }

    // ---- aides ----------------------------------------------------------

    /**
     * Actions POST proposées sur la ligne dans la liste (déjà filtrées par les Voters).
     *
     * @return list<string>
     */
    private function availableActions(KernelBrowser $client, int $id): array
    {
        $crawler = $client->request('GET', '/admin/liste/articles');
        $actions = [];
        foreach ($crawler->filter(\sprintf('#row-articles-%d form', $id)) as $node) {
            if (1 === preg_match('#/(\w+)$#', (string) (new \Symfony\Component\DomCrawler\Crawler($node))->attr('action'), $m)) {
                $actions[] = $m[1];
            }
        }

        return $actions;
    }

    private function tokenFor(KernelBrowser $client, int $id): string
    {
        $crawler = $client->request('GET', '/admin/liste/articles');
        $input = $crawler->filter(\sprintf('#row-articles-%d form input[name="_token"]', $id));

        return $input->count() > 0 ? (string) $input->attr('value') : 'aucun-jeton';
    }

    /**
     * @param array<string, string> $server
     */
    private function act(KernelBrowser $client, int $id, string $transition, string $token, array $server = []): void
    {
        $client->request('POST', \sprintf('/admin/liste/articles/%d/%s', $id, $transition), ['_token' => $token], [], $server + $this->origin());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function only(string $class): object
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $all = $manager->getRepository($class)->findAll();
        self::assertCount(1, $all);

        return $all[0];
    }
}
