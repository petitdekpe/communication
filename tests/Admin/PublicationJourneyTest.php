<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Article;
use App\Entity\ContentVersion;
use App\Entity\EditLock;
use App\Entity\PublicationHistory;
use App\Enum\AdminRole;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Scheduler\CheckScheduledPublications;
use App\Scheduler\PublishContent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Parcours éditorial complet : rédacteur, relecteur, publication programmée, versions, aperçu signé et verrou d'édition.
 */
final class PublicationJourneyTest extends WebTestCase
{
    use AdminTestTrait;
    use Factories;
    use ResetDatabase;

    public function testEditorToReviewerToScheduledPublication(): void
    {
        $client = $this->client();
        $editor = $this->user(AdminRole::Editor);
        $reviewer = $this->user(AdminRole::Reviewer);

        // 1. Le rédacteur crée un brouillon : première version enregistrée.
        $client->loginUser($editor, 'admin');
        $id = $this->createArticle($client, 'Communiqué du parcours');
        self::assertSame(PublicationStatus::Draft, $this->article($id)->getStatus());
        self::assertSame($editor->getUserIdentifier(), $this->article($id)->getCreatedBy());
        self::assertCount(1, $this->manager()->getRepository(ContentVersion::class)->findAll());

        // 2. L'en-tête du formulaire ne propose que « soumettre » ; publier est refusé même avec un jeton valide.
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('section[aria-labelledby="workflow-titre"]');
        self::assertSelectorExists($this->actionForm($id, 'submit'));
        self::assertSelectorNotExists($this->actionForm($id, 'publish'));
        self::assertSelectorNotExists($this->actionForm($id, 'reject'));
        $token = $this->token($crawler, $this->actionForm($id, 'submit').' input[name="_token"]');
        $this->flux($client, $id, 'publish', $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(PublicationStatus::Draft, $this->article($id)->getStatus());

        // 3. Soumission : le relecteur reçoit un e-mail.
        $this->flux($client, $id, 'submit', $token);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(PublicationStatus::InReview, $this->article($id)->getStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailAddressContains($this->getMailerMessage(), 'to', $reviewer->getUserIdentifier());

        // 4. Le relecteur refuse : le motif est obligatoire.
        $client->loginUser($reviewer, 'admin');
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        self::assertSelectorExists($this->actionForm($id, 'reject').' textarea[name="reason"][required]');
        $token = $this->token($crawler, $this->actionForm($id, 'reject').' input[name="_token"]');
        $this->flux($client, $id, 'reject', $token, ['reason' => '   ']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(PublicationStatus::InReview, $this->article($id)->getStatus(), 'Refus sans motif : aucun changement');
        self::assertQueuedEmailCount(0);

        $this->flux($client, $id, 'reject', $token, ['reason' => 'Chiffres à sourcer.']);
        self::assertSame(PublicationStatus::Draft, $this->article($id)->getStatus());
        self::assertQueuedEmailCount(1);
        $mail = $this->getMailerMessage();
        self::assertEmailAddressContains($mail, 'to', $editor->getUserIdentifier());
        self::assertEmailHtmlBodyContains($mail, 'Chiffres à sourcer.');

        // 5. L'historique indique qui, quand et le motif.
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $history = $crawler->filter('details ol li')->each(static fn (Crawler $li): string => trim((string) preg_replace('/\s+/', ' ', $li->text())));
        self::assertCount(2, $history);
        self::assertStringContainsString('Refusé', $history[0]);
        self::assertStringContainsString((string) $reviewer->getName(), $history[0]);
        self::assertStringContainsString('Chiffres à sourcer.', $history[0]);
        self::assertStringContainsString('Soumis à la relecture', $history[1]);

        // 6. Le rédacteur corrige (version 2) et soumet de nouveau.
        $client->loginUser($editor, 'admin');
        $this->saveArticle($client, $id, 'Communiqué du parcours corrigé');
        self::assertCount(2, $this->manager()->getRepository(ContentVersion::class)->findAll());
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $this->flux($client, $id, 'submit', $this->token($crawler, $this->actionForm($id, 'submit').' input[name="_token"]'));
        self::assertSame(PublicationStatus::InReview, $this->article($id)->getStatus());

        // 7. Le relecteur programme la publication : le contenu reste en relecture et invisible.
        $client->loginUser($reviewer, 'admin');
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $token = $this->token($crawler, $this->actionForm($id, 'publish').' input[name="_token"]');
        $this->flux($client, $id, 'publish', $token, ['scheduled_at' => '2099-01-01T10:00']);
        self::assertResponseStatusCodeSame(303);
        $article = $this->article($id);
        self::assertSame(PublicationStatus::InReview, $article->getStatus());
        self::assertNotNull($article->getScheduledAt());
        $slug = $article->translate('fr')->getSlug();
        $client->request('GET', '/fr/actualites/'.$slug);
        self::assertResponseStatusCodeSame(404);

        // Une date passée est refusée.
        $this->flux($client, $id, 'publish', $token, ['scheduled_at' => '2000-01-01T10:00']);
        self::assertSame(PublicationStatus::InReview, $this->article($id)->getStatus());

        // 8. L'échéance arrive : le Scheduler met la publication en file, le worker (Messenger) publie.
        $due = new \DateTimeImmutable('-1 minute');
        $this->manager()->createQuery('UPDATE '.Article::class.' a SET a.scheduledAt = :due WHERE a.id = :id')
            ->setParameter('due', $due)->setParameter('id', $id)->execute();

        $bus = static::getContainer()->get(MessageBusInterface::class);
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
        $bus->dispatch(new CheckScheduledPublications());
        $queued = array_map(static fn (Envelope $e): object => $e->getMessage(), $transport->getSent());
        self::assertCount(1, $queued);
        self::assertInstanceOf(PublishContent::class, $queued[0]);
        self::assertSame($id, $queued[0]->id);

        $bus->dispatch((new Envelope($queued[0]))->with(new ReceivedStamp('async')));

        $published = $this->article($id);
        self::assertSame(PublicationStatus::Published, $published->getStatus());
        self::assertNull($published->getScheduledAt());
        self::assertEquals($due->getTimestamp(), $published->getPublishedAt()?->getTimestamp(), 'La date de publication est la date programmée');
        $sent = array_filter($transport->getSent(), static fn (Envelope $e): bool => $e->getMessage() instanceof SendEmailMessage);
        self::assertCount(1, $sent, 'L\'auteur est prévenu de la publication');

        // Déjà publié : un second passage ne fait rien.
        $transport->reset();
        $bus->dispatch(new CheckScheduledPublications());
        self::assertCount(0, $transport->getSent());

        $client->request('GET', '/fr/actualites/'.$slug);
        self::assertResponseIsSuccessful();

        $transitions = array_map(
            static fn (PublicationHistory $h): string => $h->getTransition(),
            $this->manager()->getRepository(PublicationHistory::class)->findBy([], ['id' => 'ASC']),
        );
        self::assertSame(['submit', 'reject', 'submit', 'schedule', 'publish'], $transitions);
    }

    public function testVersionsCompareAndRestore(): void
    {
        $client = $this->client();
        $editor = $this->user(AdminRole::Editor);
        $client->loginUser($editor, 'admin');

        $id = $this->createArticle($client, 'Titre initial de la version');
        $this->saveArticle($client, $id, 'Titre modifié de la version');
        // Enregistrer sans rien changer ne crée pas de version.
        $this->saveArticle($client, $id, 'Titre modifié de la version');
        self::assertCount(2, $this->manager()->getRepository(ContentVersion::class)->findAll());

        $crawler = $client->request('GET', '/admin/versions/articles/'.$id.'?a=1&b=2');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('initial', $crawler->filter('del')->text());
        self::assertStringContainsString('modifié', $crawler->filter('ins')->text());

        $token = $this->token($crawler, 'form[action$="/1/restaurer"] input[name="_token"]');
        $client->request('POST', '/admin/versions/articles/'.$id.'/1/restaurer', ['_token' => 'faux'], [], $this->origin());
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/versions/articles/'.$id.'/1/restaurer', ['_token' => $token], [], $this->origin());
        self::assertResponseStatusCodeSame(303);
        $article = $this->article($id);
        self::assertSame('Titre initial de la version', $article->translate('fr')->getTitle());
        self::assertSame(PublicationStatus::Draft, $article->getStatus(), 'La restauration ne change pas l\'état de publication');
        $versions = $this->manager()->getRepository(ContentVersion::class)->findBy([], ['number' => 'ASC']);
        self::assertCount(3, $versions);
        self::assertSame(1, $versions[2]->getRestoredFrom());
    }

    public function testDraftsAreOnlyReachableThroughASignedTemporaryLink(): void
    {
        $client = $this->client();
        $article = ArticleFactory::new()->draft()->create();
        $id = (int) $article->getId();
        $slug = $article->translate('fr')->getSlug();
        $title = (string) $article->translate('fr')->getTitle();

        // Sans lien signé : ni le site public, ni les listes, ni l'aperçu.
        $client->request('GET', '/fr/actualites/'.$slug);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/fr/actualites');
        self::assertStringNotContainsString($title, (string) $client->getResponse()->getContent());
        $client->request('GET', '/fr/apercu/articles/'.$id);
        self::assertResponseStatusCodeSame(403);

        // Le lien signé est proposé dans l'en-tête du formulaire.
        $client->loginUser($this->user(AdminRole::Editor), 'admin');
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $signed = (string) $crawler->filter('input#preview-fr')->attr('value');
        self::assertStringContainsString('_expiration=', $signed);
        self::assertStringContainsString('_hash=', $signed);
        $path = (string) parse_url($signed, \PHP_URL_PATH).'?'.parse_url($signed, \PHP_URL_QUERY);

        $client->restart();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', 'Aperçu non publié');
        self::assertSelectorTextContains('h1', $title);
        self::assertStringContainsString('noindex', (string) $client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));

        // Signature altérée, lien d'un autre contenu, lien expiré : refusés.
        $client->request('GET', preg_replace('/_hash=(.)/', '_hash=x$1', $path) ?? '');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', str_replace('/articles/'.$id, '/articles/'.($id + 1), $path));
        self::assertResponseStatusCodeSame(403);
        $uriSigner = static::getContainer()->get(UriSigner::class);
        $client->request('GET', $uriSigner->sign('http://localhost/fr/apercu/articles/'.$id, time() - 60));
        self::assertResponseStatusCodeSame(403);

        // Même avec un lien valide, une ressource sans aperçu reste introuvable.
        $client->request('GET', $uriSigner->sign('http://localhost/fr/apercu/utilisateurs/'.$id, time() + 60));
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditLockWarnsWithTheHolderNameAndExpiresAfterFifteenMinutes(): void
    {
        $client = $this->client();
        $first = $this->user(AdminRole::Editor);
        $second = $this->user(AdminRole::Editor);
        $id = (int) ArticleFactory::new()->draft()->create()->getId();
        $warning = '[data-edit-lock-target="warning"]';

        // Le premier détient le verrou : pas d'avertissement pour lui.
        $client->loginUser($first, 'admin');
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        self::assertSelectorExists($warning.'[hidden]');
        $releaseToken = (string) $crawler->filter('[data-controller="edit-lock"]')->attr('data-edit-lock-token-value');

        // Le second est averti, avec le nom du premier.
        $client->loginUser($second, 'admin');
        $client->request('GET', '/admin/articles/'.$id);
        self::assertSelectorNotExists($warning.'[hidden]');
        self::assertSelectorTextContains($warning, (string) $first->getName());

        // Après 15 minutes sans activité, le verrou expire : le second le prend, le premier est averti à son tour.
        $this->manager()->createQuery('UPDATE '.EditLock::class.' l SET l.lastActivityAt = :old')
            ->setParameter('old', new \DateTimeImmutable('-16 minutes'))->execute();
        $client->request('GET', '/admin/articles/'.$id);
        self::assertSelectorExists($warning.'[hidden]');
        $client->loginUser($first, 'admin');
        $client->request('GET', '/admin/articles/'.$id);
        self::assertSelectorTextContains($warning, (string) $second->getName());

        // Le signal d'activité prolonge le verrou de son détenteur et renvoie le détenteur actuel.
        $client->loginUser($second, 'admin');
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $token = (string) $crawler->filter('[data-controller="edit-lock"]')->attr('data-edit-lock-token-value');
        $client->request('POST', '/admin/verrou/articles/'.$id, ['_token' => $token], [], $this->origin());
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"holder":null}', (string) $client->getResponse()->getContent());
        // Le premier envoie un signal alors que le second détient le verrou : il apprend qui travaille dessus.
        $client->loginUser($first, 'admin');
        $client->request('POST', '/admin/verrou/articles/'.$id, ['_token' => $releaseToken], [], $this->origin());
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(json_encode(['holder' => $second->getName()], \JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent());

        // Un jeton invalide est refusé.
        $client->request('POST', '/admin/verrou/articles/'.$id, ['_token' => 'faux'], [], $this->origin());
        self::assertResponseStatusCodeSame(403);
    }

    // ---- aides ----------------------------------------------------------

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        assert($manager instanceof EntityManagerInterface);
        $manager->clear();

        return $manager;
    }

    private function article(int $id): Article
    {
        return $this->manager()->find(Article::class, $id) ?? throw new \LogicException('Article introuvable.');
    }

    private function actionForm(int $id, string $transition): string
    {
        return \sprintf('form[action="/admin/flux/articles/%d/%s"]', $id, $transition);
    }

    /**
     * @param array<string, string> $extra
     */
    private function flux(KernelBrowser $client, int $id, string $transition, string $token, array $extra = []): void
    {
        $client->request('POST', \sprintf('/admin/flux/articles/%d/%s', $id, $transition), ['_token' => $token] + $extra, [], $this->origin());
    }

    private function createArticle(KernelBrowser $client, string $title): int
    {
        $crawler = $client->request('GET', '/admin/articles/nouveau');
        $client->request('POST', '/admin/articles/nouveau', ['article_admin' => $this->articleFields($title) + [
            '_token' => $this->token($crawler, 'input[name="article_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);

        return (int) $this->manager()->getRepository(Article::class)->findOneBy([], ['id' => 'DESC'])?->getId();
    }

    private function saveArticle(KernelBrowser $client, int $id, string $title): void
    {
        $crawler = $client->request('GET', '/admin/articles/'.$id);
        $client->request('POST', '/admin/articles/'.$id, ['article_admin' => $this->articleFields($title) + [
            '_token' => $this->token($crawler, 'input[name="article_admin[_token]"]'),
        ]], [], $this->origin());
        self::assertResponseStatusCodeSame(303);
    }

    /**
     * @return array<string, mixed>
     */
    private function articleFields(string $title): array
    {
        return [
            'type' => 'communique',
            'category' => '',
            'translations' => ['fr' => ['title' => $title, 'excerpt' => 'Chapô', 'body' => '<p>Texte</p>'], 'en' => ['title' => '', 'excerpt' => '', 'body' => '']],
        ];
    }
}
