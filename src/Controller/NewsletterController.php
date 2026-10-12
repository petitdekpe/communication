<?php

declare(strict_types=1);

namespace App\Controller;

use App\Newsletter\NewsletterService;
use App\Newsletter\NewsletterSubscription;
use App\Newsletter\NewsletterSubscriptionType;
use App\ServiceRequest\RequestRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lettre d'information : inscription (double opt-in), confirmation et désinscription en un clic.
 * Les jetons des liens sont aléatoires ; aucune adresse électronique ne figure dans une URL.
 */
final class NewsletterController extends AbstractController
{
    private const string TOKEN = '[0-9a-f]{64}';

    #[Route(path: ['fr' => '/lettre-information', 'en' => '/newsletter'], name: 'app_newsletter', defaults: ['_sensitive' => true], methods: ['GET', 'POST'])]
    public function subscribe(Request $request, NewsletterService $newsletter, RequestRateLimiter $limiter, TranslatorInterface $translator): Response
    {
        $data = new NewsletterSubscription();
        $form = $this->createForm(NewsletterSubscriptionType::class, $data);
        $form->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$limiter->consumeNewsletter($request)->isAccepted()) {
                $form->addError(new FormError($translator->trans('request.error.too_many')));
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $newsletter->subscribe((string) $data->email, $request->getLocale());

                // Même réponse quel que soit l'état de l'adresse.
                return $this->render('newsletter/subscribed.html.twig');
            }
        }

        return $this->render('newsletter/index.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(
        path: ['fr' => '/lettre-information/confirmation/{token}', 'en' => '/newsletter/confirm/{token}'],
        name: 'app_newsletter_confirm',
        requirements: ['token' => self::TOKEN],
        defaults: ['_sensitive' => true],
        methods: ['GET'],
    )]
    public function confirm(string $token, NewsletterService $newsletter): Response
    {
        $confirmed = $newsletter->confirm($token);

        return $this->render('newsletter/confirmed.html.twig', ['confirmed' => $confirmed], new Response(status: $confirmed ? Response::HTTP_OK : Response::HTTP_NOT_FOUND));
    }

    /**
     * Désinscription en un clic : le lien suffit (GET). Le POST répond au « List-Unsubscribe=One-Click » des
     * clients de messagerie (RFC 8058).
     */
    #[Route(
        path: ['fr' => '/lettre-information/desinscription/{token}', 'en' => '/newsletter/unsubscribe/{token}'],
        name: 'app_newsletter_unsubscribe',
        requirements: ['token' => self::TOKEN],
        defaults: ['_sensitive' => true],
        methods: ['GET', 'POST'],
    )]
    public function unsubscribe(string $token, NewsletterService $newsletter): Response
    {
        $done = $newsletter->unsubscribe($token);

        return $this->render('newsletter/unsubscribed.html.twig', ['done' => $done], new Response(status: $done ? Response::HTTP_OK : Response::HTTP_NOT_FOUND));
    }
}
