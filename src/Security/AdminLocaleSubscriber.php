<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Langue de travail de l'administration (français ou anglais), choisie dans la barre supérieure et conservée en
 * session : elle détermine l'onglet de langue ouvert par défaut et la langue des titres affichés dans les listes.
 */
final class AdminLocaleSubscriber
{
    public const string SESSION_KEY = 'admin_locale';

    /** @var list<string> */
    public const array LOCALES = ['fr', 'en'];

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/admin') || !$request->hasSession()) {
            return;
        }

        $locale = $request->getSession()->get(self::SESSION_KEY);
        if (\is_string($locale) && \in_array($locale, self::LOCALES, true)) {
            $request->setLocale($locale);
        }
    }
}
