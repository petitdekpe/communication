<?php

declare(strict_types=1);

namespace App\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cache HTTP des pages publiées (routes déclarées avec defaults: ['_cacheable' => true] : accueil, pages, plan du site).
 *
 * Seules les réponses 200 destinées à un visiteur sans session sont gardées : HTML complet dans cache.page_html, étiqueté
 * par les données affichées (voir CacheTag). Une publication ou une modification invalide les étiquettes concernées
 * (PageCacheInvalidator) : la page à jour est servie dès la requête suivante, sans attendre d'expiration.
 *
 * Les en-têtes envoyés aux navigateurs et aux serveurs intermédiaires (CDN, proxy) sont prudents : le navigateur
 * revalide à chaque visite (ETag, 304), un cache partagé garde la page 60 secondes au plus et peut être purgé par
 * l'en-tête Cache-Tag (étiquettes). Une page publiée apparaît donc en moins d'une minute, même sans purge.
 */
final class PageCacheSubscriber implements EventSubscriberInterface
{
    public const string KEY_ATTRIBUTE = '_page_cache_key';

    /** Durée de vie maximale d'une page dans le cache applicatif : filet de sécurité, l'invalidation par étiquettes fait le travail. */
    private const int LIFETIME = 3600;

    /** Durée de conservation dans un cache partagé (CDN, proxy) en secondes : borne la fraîcheur sans purge. */
    private const int SHARED_MAX_AGE = 60;

    /** En-têtes qui ne sont pas rejoués depuis le cache (recalculés à chaque réponse). */
    private const array VOLATILE_HEADERS = ['date', 'cache-control', 'etag', 'set-cookie', 'expires', 'last-modified', 'age', 'x-page-cache', 'x-debug-token', 'x-debug-token-link'];

    public function __construct(
        #[Autowire(service: 'cache.page_html')]
        private readonly TagAwareAdapterInterface $cache,
        private readonly RequestTags $tags,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.page_cache%')]
        private readonly bool $enabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 5],
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->eligible($event->isMainRequest(), $request)) {
            return;
        }

        $key = $this->key($request);
        try {
            $item = $this->cache->getItem($key);
        } catch (\Throwable $exception) {
            $this->logger->warning('Cache de pages illisible : {message}', ['message' => $exception->getMessage()]);

            return;
        }

        if (!$item->isHit()) {
            $request->attributes->set(self::KEY_ATTRIBUTE, $key);

            return;
        }

        /** @var array{content: string, status: int, headers: array<string, list<string|null>>, tags: list<string>} $stored */
        $stored = $item->get();
        $response = new Response($stored['content'], $stored['status'], $stored['headers']);
        $this->publicHeaders($response, $stored['tags']);
        $response->headers->set('X-Page-Cache', 'HIT');
        $response->isNotModified($request);

        $event->setResponse($response);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $key = $request->attributes->get(self::KEY_ATTRIBUTE);
        if (!$event->isMainRequest() || !\is_string($key)) {
            return;
        }

        $response = $event->getResponse();
        $session = $request->hasSession() ? $request->getSession() : null;
        if (Response::HTTP_OK !== $response->getStatusCode() || [] !== $response->headers->getCookies() || (null !== $session && $session->isStarted())) {
            return;
        }

        $tags = array_values(array_unique([...$this->tags->all(), CacheTag::MENU, CacheTag::SETTINGS]));
        $this->publicHeaders($response, $tags);
        $response->headers->set('X-Page-Cache', 'MISS');

        try {
            $item = $this->cache->getItem($key);
            $item->set([
                'content' => (string) $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => array_diff_key($response->headers->all(), array_flip(self::VOLATILE_HEADERS)),
                'tags' => $tags,
            ]);
            $item->tag($tags);
            $item->expiresAfter(self::LIFETIME);
            $this->cache->save($item);
        } catch (\Throwable $exception) {
            $this->logger->warning('Cache de pages inaccessible en écriture : {message}', ['message' => $exception->getMessage()]);
        }

        $response->isNotModified($request);
    }

    private function eligible(bool $main, Request $request): bool
    {
        return $this->enabled
            && $main
            && $request->isMethod('GET')
            && true === $request->attributes->get('_cacheable')
            && true !== $request->attributes->get('_sensitive')
            && !$request->hasPreviousSession()
            && !$request->headers->has('Authorization');
    }

    private function key(Request $request): string
    {
        return 'page.'.sha1($request->getLocale().'|'.$request->getPathInfo().'|'.$request->getQueryString());
    }

    /**
     * @param list<string> $tags
     */
    private function publicHeaders(Response $response, array $tags): void
    {
        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(self::SHARED_MAX_AGE);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(md5((string) $response->getContent()));
        $response->headers->set('Cache-Tag', implode(',', $tags));
    }
}
