<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Limite par adresse IP (fenêtre glissante) : envois de formulaire de démarche, consultations du suivi,
 * contributions aux consultations, messages de contact et inscriptions à la lettre d'information.
 * Chaque tentative compte, valide ou non : c'est ce qui freine le sondage de numéros de suivi.
 */
final class RequestRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.service_request_submit')]
        private readonly RateLimiterFactory $submitLimiter,
        #[Autowire(service: 'limiter.tracking_lookup')]
        private readonly RateLimiterFactory $trackingLimiter,
        #[Autowire(service: 'limiter.consultation_contribution')]
        private readonly RateLimiterFactory $contributionLimiter,
        #[Autowire(service: 'limiter.newsletter_subscribe')]
        private readonly RateLimiterFactory $newsletterLimiter,
        #[Autowire(service: 'limiter.contact_message')]
        private readonly RateLimiterFactory $contactLimiter,
    ) {
    }

    public function consumeSubmission(Request $request): RateLimit
    {
        return $this->submitLimiter->create($this->key($request))->consume();
    }

    public function consumeTracking(Request $request): RateLimit
    {
        return $this->trackingLimiter->create($this->key($request))->consume();
    }

    public function consumeContribution(Request $request): RateLimit
    {
        return $this->contributionLimiter->create($this->key($request))->consume();
    }

    public function consumeNewsletter(Request $request): RateLimit
    {
        return $this->newsletterLimiter->create($this->key($request))->consume();
    }

    public function consumeContact(Request $request): RateLimit
    {
        return $this->contactLimiter->create($this->key($request))->consume();
    }

    private function key(Request $request): string
    {
        return $request->getClientIp() ?? 'unknown';
    }
}
