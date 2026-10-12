<?php

declare(strict_types=1);

namespace App\Newsletter;

use App\Entity\NewsletterSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Message de confirmation du double opt-in : un seul lien, qui active l'abonnement. Il contient aussi le lien
 * de désinscription, pour qu'une personne inscrite à son insu puisse l'annuler sans rien confirmer.
 */
final class NewsletterMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        $locale = $subscriber->getLocale();

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $this->translator->trans('site.name', locale: $locale)))
            ->to($subscriber->getEmail())
            ->subject($this->translator->trans('newsletter.mail.subject', locale: $locale))
            ->htmlTemplate('email/newsletter_confirmation.html.twig')
            ->textTemplate('email/newsletter_confirmation.txt.twig')
            ->context([
                'locale' => $locale,
                'confirmToken' => $subscriber->getConfirmToken(),
                'unsubscribeToken' => $subscriber->getUnsubscribeToken(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Journal sans adresse e-mail.
            $this->logger->error('Message de confirmation de la lettre d\'information non envoyé (abonné {id}).', [
                'id' => $subscriber->getId(),
                'exception' => $exception,
            ]);
        }
    }
}
