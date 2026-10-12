<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionStatus;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Abonné à la lettre d'information, avec double opt-in : l'abonnement n'est actif qu'après clic sur le lien
 * envoyé par e-mail. Les deux jetons sont aléatoires (32 octets) : l'un confirme, l'autre désinscrit en un clic.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_confirm_token', columns: ['confirm_token'])]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_unsubscribe_token', columns: ['unsubscribe_token'])]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** En minuscules. */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(length: 16, enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::Pending;

    #[ORM\Column(length: 64)]
    private string $confirmToken;

    #[ORM\Column(length: 64)]
    private string $unsubscribeToken;

    /** Moment de la demande d'inscription (consentement). */
    #[ORM\Column]
    private \DateTimeImmutable $subscribedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct(string $email, string $locale, \DateTimeImmutable $now)
    {
        $this->email = mb_strtolower(trim($email));
        $this->locale = $locale;
        $this->subscribedAt = $now;
        $this->confirmToken = bin2hex(random_bytes(32));
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getConfirmToken(): string
    {
        return $this->confirmToken;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getSubscribedAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function confirm(\DateTimeImmutable $now): static
    {
        if (SubscriptionStatus::Confirmed !== $this->status) {
            $this->status = SubscriptionStatus::Confirmed;
            $this->confirmedAt = $now;
            $this->unsubscribedAt = null;
        }

        return $this;
    }

    public function unsubscribe(\DateTimeImmutable $now): static
    {
        if (SubscriptionStatus::Unsubscribed !== $this->status) {
            $this->status = SubscriptionStatus::Unsubscribed;
            $this->unsubscribedAt = $now;
        }

        return $this;
    }

    /** Nouvelle demande d'une adresse en attente ou désinscrite : nouveaux jetons, retour à « en attente ». */
    public function restart(string $locale, \DateTimeImmutable $now): static
    {
        $this->locale = $locale;
        $this->status = SubscriptionStatus::Pending;
        $this->subscribedAt = $now;
        $this->confirmedAt = null;
        $this->unsubscribedAt = null;
        $this->confirmToken = bin2hex(random_bytes(32));
        $this->unsubscribeToken = bin2hex(random_bytes(32));

        return $this;
    }
}
