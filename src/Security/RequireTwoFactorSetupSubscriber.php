<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Double authentification obligatoire : un utilisateur connecté qui n'a pas encore enrôlé son application TOTP
 * est renvoyé vers la page d'enrôlement pour toute adresse /admin, sauf la déconnexion et l'enrôlement lui-même.
 * (Un utilisateur qui a un secret passe, lui, par le formulaire de code : le firewall l'impose avant tout accès.).
 */
final class RequireTwoFactorSetupSubscriber
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/admin')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User || $token instanceof TwoFactorTokenInterface || $user->isTotpAuthenticationEnabled()) {
            return;
        }

        $setup = $this->urlGenerator->generate('app_admin_2fa_setup');
        $logout = $this->urlGenerator->generate('app_admin_logout');
        if ($path === $setup || $path === $logout) {
            return;
        }

        $event->setResponse(new RedirectResponse($setup));
    }
}
