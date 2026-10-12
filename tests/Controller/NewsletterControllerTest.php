<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\NewsletterSubscriber;
use App\Enum\SubscriptionStatus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Lettre d'information : double opt-in, désinscription en un clic, export des abonnés confirmés.
 */
final class NewsletterControllerTest extends WebTestCase
{
    use Factories;
    use FormTestTrait;
    use ResetDatabase;

    private const string EMAIL = 'lecteur@example.org';

    public function testSubscriptionRequiresConfirmationByEmail(): void
    {
        $client = $this->client();

        $this->subscribe($client, self::EMAIL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Si cette adresse peut être inscrite');
        $subscriber = $this->subscriber();
        self::assertSame(SubscriptionStatus::Pending, $subscriber->getStatus(), 'Pas d\'abonnement actif avant la confirmation');

        self::assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        self::assertEmailAddressContains($email, 'to', self::EMAIL);
        self::assertEmailTextBodyContains($email, '/fr/lettre-information/confirmation/'.$subscriber->getConfirmToken());
        self::assertEmailHtmlBodyContains($email, '/fr/lettre-information/desinscription/'.$subscriber->getUnsubscribeToken());
        self::assertEmailHtmlBodyNotContains($email, self::EMAIL, 'Aucune adresse dans les liens');
    }

    public function testConfirmationLinkActivatesTheSubscription(): void
    {
        $client = $this->client();
        $this->subscribe($client, self::EMAIL);
        $subscriber = $this->subscriber();

        $client->request('GET', '/fr/lettre-information/confirmation/'.$subscriber->getConfirmToken());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Abonnement confirmé');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $confirmed = $this->subscriber();
        self::assertSame(SubscriptionStatus::Confirmed, $confirmed->getStatus());
        self::assertNotNull($confirmed->getConfirmedAt());

        // Un clic répété reste sans effet.
        $client->request('GET', '/fr/lettre-information/confirmation/'.$subscriber->getConfirmToken());
        self::assertResponseIsSuccessful();
    }

    public function testUnknownOrMalformedTokensAreRejected(): void
    {
        $client = $this->client();

        $client->request('GET', '/fr/lettre-information/confirmation/'.str_repeat('a', 64));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/fr/lettre-information/desinscription/'.str_repeat('b', 64));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/fr/lettre-information/confirmation/not-a-token');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnsubscribeIsOneClickAndAlsoAnswersOneClickPost(): void
    {
        $client = $this->client();
        $this->subscribe($client, self::EMAIL);
        $subscriber = $this->subscriber();
        $client->request('GET', '/fr/lettre-information/confirmation/'.$subscriber->getConfirmToken());

        $client->request('GET', '/fr/lettre-information/desinscription/'.$subscriber->getUnsubscribeToken());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Désinscription effectuée');
        self::assertSame(SubscriptionStatus::Unsubscribed, $this->subscriber()->getStatus());

        // Désinscrit : le lien de confirmation ne le réabonne pas.
        $client->request('GET', '/fr/lettre-information/confirmation/'.$subscriber->getConfirmToken());
        self::assertResponseStatusCodeSame(404);
        self::assertSame(SubscriptionStatus::Unsubscribed, $this->subscriber()->getStatus());

        $client->request('POST', '/fr/lettre-information/desinscription/'.$subscriber->getUnsubscribeToken(), ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseIsSuccessful();
    }

    public function testResponseDoesNotRevealWhetherAnAddressIsAlreadySubscribed(): void
    {
        $client = $this->client();
        $this->subscribe($client, self::EMAIL);
        $first = $this->getMailerMessage();
        self::assertNotNull($first);
        $client->request('GET', '/fr/lettre-information/confirmation/'.$this->subscriber()->getConfirmToken());

        // Adresse déjà confirmée : même réponse, aucun nouveau message.
        $this->subscribe($client, strtoupper(self::EMAIL));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Si cette adresse peut être inscrite');
        self::assertQueuedEmailCount(0);
        self::assertSame(SubscriptionStatus::Confirmed, $this->subscriber()->getStatus());
    }

    public function testPendingAddressIsNotMailedAgainWithinFiveMinutes(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $this->subscribe($client, self::EMAIL);
        self::assertQueuedEmailCount(1);

        $this->subscribe($client, self::EMAIL);
        self::assertResponseIsSuccessful();
        self::assertQueuedEmailCount(0);
    }

    public function testHoneypotAndInvalidEmailAreRejected(): void
    {
        $client = $this->client();

        $this->subscribe($client, self::EMAIL, ['website' => 'spam']);
        self::assertResponseStatusCodeSame(422);

        $this->subscribe($client, 'pas-un-email');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'adresse électronique');
        self::assertCount(0, static::getContainer()->get('doctrine')->getRepository(NewsletterSubscriber::class)->findAll());
        self::assertQueuedEmailCount(0);
    }

    public function testSubscriptionsAreRateLimited(): void
    {
        $client = $this->client();
        $client->disableReboot();

        for ($i = 0; $i < 5; ++$i) {
            $this->subscribe($client, \sprintf('a%d@example.org', $i));
            self::assertResponseIsSuccessful();
        }
        $this->subscribe($client, 'a6@example.org');

        self::assertSelectorTextContains('[role="alert"]', 'Trop de tentatives');
    }

    public function testEnglishFlowSendsEnglishMessages(): void
    {
        $client = $this->client();

        $client->request('GET', '/en/newsletter');
        $crawler = $client->getCrawler();
        $client->request('POST', '/en/newsletter', ['newsletter' => ['email' => self::EMAIL, 'website' => '', '_token' => $this->token($crawler, 'newsletter')]], [], $this->origin());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'confirmation message');
        self::assertEmailTextBodyContains($this->getMailerMessage(), '/en/newsletter/confirm/');
        self::assertSame('en', $this->subscriber()->getLocale());
    }

    public function testExportListsOnlyConfirmedSubscribers(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $this->subscribe($client, 'confirme@example.org');
        $client->request('GET', '/fr/lettre-information/confirmation/'.$this->subscriber('confirme@example.org')->getConfirmToken());
        $this->subscribe($client, 'attente@example.org');
        $this->subscribe($client, 'parti@example.org');
        $leaver = $this->subscriber('parti@example.org');
        $client->request('GET', '/fr/lettre-information/confirmation/'.$leaver->getConfirmToken());
        $client->request('GET', '/fr/lettre-information/desinscription/'.$leaver->getUnsubscribeToken());

        $tester = new CommandTester((new \Symfony\Bundle\FrameworkBundle\Console\Application($client->getKernel()))->find('app:newsletter:export'));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('confirme@example.org', $output);
        self::assertStringNotContainsString('attente@example.org', $output);
        self::assertStringNotContainsString('parti@example.org', $output);
        self::assertStringContainsString('email;language;subscribed_at;confirmed_at', $output);
    }

    // ---- aides ----------------------------------------------------------

    /**
     * @param array<string, string> $overrides
     */
    private function subscribe(KernelBrowser $client, string $email, array $overrides = []): void
    {
        $client->request('GET', '/fr/lettre-information');
        $crawler = $client->getCrawler();
        $client->request('POST', '/fr/lettre-information', ['newsletter' => array_replace([
            'email' => $email,
            'website' => '',
            '_token' => $this->token($crawler, 'newsletter'),
        ], $overrides)], [], $this->origin());
    }

    private function subscriber(string $email = self::EMAIL): NewsletterSubscriber
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $subscriber = $manager->getRepository(NewsletterSubscriber::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(NewsletterSubscriber::class, $subscriber);

        return $subscriber;
    }
}
