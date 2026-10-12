<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ContactMessage;
use App\Factory\PageFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * « Nous contacter » : page éditoriale et formulaire (champs du document éditorial, consentement, piège à robots, limite).
 */
final class ContactControllerTest extends WebTestCase
{
    use Factories;
    use FormTestTrait;
    use ResetDatabase;

    public function testPageShowsTheEditorialBodyAndTheForm(): void
    {
        $client = $this->client();
        $this->contactPage();

        foreach (['fr', 'en'] as $locale) {
            $client->request('GET', '/'.$locale.'/contact');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', 'Contact');
            self::assertSelectorExists('form[name="contact"]');
            foreach (['name', 'email', 'phone', 'subject', 'message', 'consent'] as $field) {
                self::assertSelectorExists(\sprintf('[name="contact[%s]"]', $field));
            }
        }
    }

    public function testContactCardsHaveNoBorder(): void
    {
        $client = $this->client();
        $this->contactPage('<p>Intro</p><h2>Coordonnées</h2><p>01 BP 120</p><h2>Contacter un service</h2><p>x</p>');

        $crawler = $client->request('GET', '/fr/contact');

        self::assertCount(2, $crawler->filter('article section.rounded-card'));
        self::assertCount(0, $crawler->filter('article section.ring-1, article section.border'), 'Cartes sans bordure');
    }

    public function testValidMessageIsStored(): void
    {
        $client = $this->client();
        $this->contactPage();
        $client->request('GET', '/fr/contact');

        $this->send($client, []);

        self::assertResponseStatusCodeSame(303);
        $messages = $this->messages();
        self::assertCount(1, $messages);
        self::assertSame('Amina Test', $messages[0]->getName());
        self::assertSame('amina@example.org', $messages[0]->getEmail());
        self::assertNull($messages[0]->getPhone());
        self::assertSame('fr', $messages[0]->getLocale());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'bien été envoyée');
    }

    public function testRequiredFieldsAndHoneypotAreEnforced(): void
    {
        $client = $this->client();
        $this->contactPage();
        $client->request('GET', '/fr/contact');

        $this->send($client, ['website' => 'spam']);
        self::assertResponseStatusCodeSame(422);

        $this->send($client, ['name' => '', 'email' => 'pas-un-courriel', 'message' => 'court', 'consent' => null]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'obligatoire');
        self::assertSelectorTextContains('[role="alert"]', 'pas valide');
        self::assertSelectorTextContains('[role="alert"]', 'accepter le traitement');
        self::assertSame([], $this->messages());
    }

    public function testMessagesAreRateLimited(): void
    {
        $client = $this->client();
        $client->disableReboot();
        $this->contactPage();
        $client->request('GET', '/fr/contact');

        for ($i = 0; $i < 5; ++$i) {
            $this->send($client, ['name' => '']);
            self::assertResponseStatusCodeSame(422);
        }
        $this->send($client, ['name' => '']);

        self::assertSelectorTextContains('[role="alert"]', 'Trop de tentatives');
    }

    private function contactPage(string $bodyFr = '<p>Coordonnées</p>'): void
    {
        PageFactory::new()->withTexts([
            'fr' => ['title' => 'Contact', 'body' => $bodyFr],
            'en' => ['title' => 'Contact', 'body' => '<p>Contact details</p>'],
        ])->create();
    }

    /**
     * @param array<string, mixed> $override
     */
    private function send(KernelBrowser $client, array $override): void
    {
        $crawler = $client->request('GET', '/fr/contact');
        $values = array_merge([
            'name' => 'Amina Test',
            'email' => 'amina@example.org',
            'phone' => '',
            'subject' => 'Demande d\'information',
            'message' => 'Bonjour, ceci est un message de test.',
            'consent' => '1',
            'website' => '',
            '_token' => $this->token($crawler, 'contact'),
        ], $override);
        $client->request('POST', '/fr/contact', ['contact' => array_filter($values, static fn ($v): bool => null !== $v)], [], $this->origin());
    }

    /**
     * @return list<ContactMessage>
     */
    private function messages(): array
    {
        return static::getContainer()->get('doctrine')->getRepository(ContactMessage::class)->findAll();
    }
}
