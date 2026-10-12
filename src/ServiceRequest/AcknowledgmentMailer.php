<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Entity\ServiceRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Accusé de réception : numéro de suivi et lien vers la page de suivi. Le message ne reprend aucune autre
 * donnée personnelle, et le lien ne porte ni le numéro ni l'e-mail (ils se saisissent sur la page).
 */
final class AcknowledgmentMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function sendAcknowledgment(ServiceRequest $request): void
    {
        $locale = $request->getLocale();
        $translation = $request->getService()->translate($locale);

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $this->translator->trans('site.name', locale: $locale)))
            ->to($request->getEmail())
            ->subject($this->translator->trans('request.mail.subject', ['number' => $request->getTrackingNumber()], locale: $locale))
            ->htmlTemplate('email/request_acknowledgment.html.twig')
            ->textTemplate('email/request_acknowledgment.txt.twig')
            ->context([
                'locale' => $locale,
                'trackingNumber' => $request->getTrackingNumber(),
                'serviceTitle' => $translation->getTitle(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Journal sans adresse e-mail : seul le numéro de suivi permet de retrouver la demande.
            $this->logger->error('Accusé de réception non envoyé pour la demande {number}.', [
                'number' => $request->getTrackingNumber(),
                'exception' => $exception,
            ]);
        }
    }
}
