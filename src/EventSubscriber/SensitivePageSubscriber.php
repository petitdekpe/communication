<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pages qui affichent ou collectent des données personnelles (route déclarée avec defaults: ['_sensitive' => true]) :
 * jamais mises en cache, jamais indexées, et leur adresse n'est pas transmise aux sites externes (en-tête Referer).
 */
final class SensitivePageSubscriber
{
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || true !== $event->getRequest()->attributes->get('_sensitive')) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store, private');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
}
