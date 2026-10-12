<?php

declare(strict_types=1);

namespace App\Contact;

use App\Entity\ContactMessage;
use App\Settings\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Prévient le Ministère d'un nouveau message, à l'adresse de contact des paramètres du site (la réponse part vers
 * l'expéditeur). Sans adresse de contact renseignée, le message reste lisible dans l'administration.
 */
final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SiteSettings $settings,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function notify(ContactMessage $message): void
    {
        $to = $this->settings->get(SiteSettings::CONTACT_EMAIL);
        if (null === $to) {
            return;
        }

        $body = \sprintf(
            "Nom : %s\nCourriel : %s\nTéléphone : %s\nLangue : %s\n\n%s\n",
            $message->getName(),
            $message->getEmail(),
            $message->getPhone() ?? '-',
            $message->getLocale(),
            $message->getMessage(),
        );
        $email = (new Email())
            ->from(new Address($this->from, 'Ministère de la Communication'))
            ->to($to)
            ->replyTo(new Address($message->getEmail(), $message->getName()))
            ->subject('[Contact] '.$message->getSubject())
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Journal sans donnée personnelle : le message reste enregistré.
            $this->logger->error('Notification d\'un message de contact non envoyée (message {id}).', ['id' => $message->getId(), 'exception' => $exception]);
        }
    }
}
