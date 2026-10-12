<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Freine la recherche par force brute du code TOTP à 6 chiffres : scheb/2fa-bundle ne limite pas les essais.
 * Chaque envoi du formulaire de code compte (5 par session de connexion, 30 par adresse IP, sur 15 minutes) ;
 * au-delà, la requête est renvoyée vers le formulaire sans que le code soit même vérifié (le bon code est refusé aussi).
 * S'exécute avant le pare-feu (priorité supérieure à la sienne).
 */
final class TwoFactorThrottleSubscriber
{
    public function __construct(
        #[Autowire(service: 'limiter.two_factor_session')]
        private readonly RateLimiterFactory $sessionLimiter,
        #[Autowire(service: 'limiter.two_factor_ip')]
        private readonly RateLimiterFactory $ipLimiter,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod(Request::METHOD_POST) || '/admin/2fa_check' !== $request->getPathInfo()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession()->getId() : '';
        $bySession = $this->sessionLimiter->create('s-'.hash('sha256', $session))->consume();
        $byIp = $this->ipLimiter->create('i-'.($request->getClientIp() ?? 'unknown'))->consume();

        if (!$bySession->isAccepted() || !$byIp->isAccepted()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('2fa_login')));
        }
    }
}
