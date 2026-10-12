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
 * E-mail adressé au demandeur à chaque changement de statut de sa demande, dans sa langue. Il porte le numéro de suivi,
 * le nouveau statut et le lien de la page de suivi ; le commentaire interne du service presse n'y figure jamais.
 */
final class StatusChangeMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function send(ServiceRequest $request): void
    {
        $locale = $request->getLocale();

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $this->translator->trans('site.name', locale: $locale)))
            ->to($request->getEmail())
            ->subject($this->translator->trans('request.status_mail.subject', ['number' => $request->getTrackingNumber()], locale: $locale))
            ->htmlTemplate('email/request_status.html.twig')
            ->textTemplate('email/request_status.txt.twig')
            ->context([
                'locale' => $locale,
                'trackingNumber' => $request->getTrackingNumber(),
                'serviceTitle' => $request->getService()->translate($locale)->getTitle(),
                'status' => $request->getStatus(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Le changement de statut est déjà enregistré : l'échec d'envoi ne le défait pas. Journal sans adresse e-mail.
            $this->logger->error('E-mail de changement de statut non envoyé pour la demande {number}.', [
                'number' => $request->getTrackingNumber(),
                'exception' => $exception,
            ]);
        }
    }
}
