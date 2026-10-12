<?php

declare(strict_types=1);

namespace App\Newsletter;

use App\Entity\NewsletterSubscriber;
use App\Enum\SubscriptionStatus;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Inscription avec double opt-in, confirmation et désinscription.
 *
 * La réponse à une demande d'inscription ne dépend jamais de l'état de l'adresse : elle ne permet pas de savoir
 * si une adresse est déjà abonnée. Une adresse déjà confirmée ne reçoit rien ; une adresse en attente ne reçoit pas
 * un nouveau message avant 5 minutes (pas d'inondation d'une boîte par des demandes répétées).
 */
final class NewsletterService
{
    private const string RESEND_DELAY = '5 minutes';

    public function __construct(
        private readonly NewsletterSubscriberRepository $subscribers,
        private readonly EntityManagerInterface $entityManager,
        private readonly NewsletterMailer $mailer,
        private readonly ClockInterface $clock,
    ) {
    }

    public function subscribe(string $email, string $locale): void
    {
        $now = $this->clock->now();
        $subscriber = $this->subscribers->findByEmail($email);

        if (null === $subscriber) {
            $subscriber = new NewsletterSubscriber($email, $locale, $now);
            $this->entityManager->persist($subscriber);
        } elseif (SubscriptionStatus::Confirmed === $subscriber->getStatus()) {
            return;
        } elseif (SubscriptionStatus::Pending === $subscriber->getStatus() && $subscriber->getSubscribedAt() > $now->modify('-'.self::RESEND_DELAY)) {
            return;
        } else {
            $subscriber->restart($locale, $now);
        }

        $this->entityManager->flush();
        $this->mailer->sendConfirmation($subscriber);
    }

    /**
     * @return bool vrai si le jeton correspond à un abonné (l'abonnement est alors confirmé)
     */
    public function confirm(string $token): bool
    {
        $subscriber = $this->subscribers->findOneBy(['confirmToken' => $token]);
        if (null === $subscriber || SubscriptionStatus::Unsubscribed === $subscriber->getStatus()) {
            return false;
        }

        $subscriber->confirm($this->clock->now());
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return bool vrai si le jeton correspond à un abonné (désinscrit, sans autre confirmation)
     */
    public function unsubscribe(string $token): bool
    {
        $subscriber = $this->subscribers->findOneBy(['unsubscribeToken' => $token]);
        if (null === $subscriber) {
            return false;
        }

        $subscriber->unsubscribe($this->clock->now());
        $this->entityManager->flush();

        return true;
    }
}
