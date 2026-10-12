<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Date du dernier accès : renseignée quand l'utilisateur est complètement authentifié, c'est-à-dire après la
 * double authentification (ou dès la connexion pour un compte qui n'a pas encore enrôlé son application).
 */
final class LastAccessSubscriber
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User && !$user->isTotpAuthenticationEnabled()) {
            $this->touch($user);
        }
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::COMPLETE)]
    public function onTwoFactorComplete(TwoFactorAuthenticationEvent $event): void
    {
        $user = $event->getToken()->getUser();
        if ($user instanceof User) {
            $this->touch($user);
        }
    }

    private function touch(User $user): void
    {
        $user->setLastLoginAt($this->clock->now());
        $this->entityManager->flush();
    }
}
