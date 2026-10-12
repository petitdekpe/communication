<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use App\Repository\ServiceRequestRepository;
use App\Routing\AlternateUrlResolver;
use App\ServiceRequest\RequestRateLimiter;
use App\ServiceRequest\ServiceForm;
use App\ServiceRequest\ServiceFormRegistry;
use App\ServiceRequest\ServiceRequestSubmitter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire interne d'une démarche en ligne. Aucune donnée saisie ne passe par l'URL : l'envoi est un POST,
 * puis la confirmation lit le numéro de suivi dans la session.
 */
final class ServiceRequestController extends AbstractController
{
    /** Clé de session : numéro de suivi de la dernière demande déposée. */
    private const string SESSION_KEY = 'service_request.tracking_number';

    #[Route(
        path: ['fr' => '/demarches/{slug}/demande', 'en' => '/services/{slug}/request'],
        name: 'app_service_request',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        defaults: ['_sensitive' => true],
        methods: ['GET', 'POST'],
    )]
    public function request(
        string $slug,
        Request $request,
        ServiceRepository $services,
        ServiceFormRegistry $forms,
        RequestRateLimiter $limiter,
        ServiceRequestSubmitter $submitter,
        TranslatorInterface $translator,
        ClockInterface $clock,
    ): Response {
        [$service, $definition] = $this->resolve($slug, $request, $services, $forms, $clock);

        $locale = $request->getLocale();
        $translation = $service->translate($locale);
        if ($request->isMethod(Request::METHOD_GET) && $translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_service_request', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $data = $definition->createData();
        $form = $this->createForm($definition->formType(), $data);
        $form->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$limiter->consumeSubmission($request)->isAccepted()) {
                $form->addError(new FormError($translator->trans('request.error.too_many')));
            } elseif (!$form->isSubmitted()) {
                // Corps de requête vide : l'ensemble des pièces dépasse la taille maximale acceptée par PHP.
                $form->addError(new FormError($translator->trans('request.error.too_large')));
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $submitted = $submitter->submit($service, $data, $locale);
                $request->getSession()->set(self::SESSION_KEY, $submitted->getTrackingNumber());

                return $this->redirectToRoute('app_service_request_done', ['slug' => $translation->getSlug()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('service/request.html.twig', [
            'service' => $service,
            'translation' => $translation,
            'form' => $form,
        ], new Response(status: $request->isMethod(Request::METHOD_POST) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(
        path: ['fr' => '/demarches/{slug}/demande/confirmation', 'en' => '/services/{slug}/request/confirmation'],
        name: 'app_service_request_done',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        defaults: ['_sensitive' => true],
        methods: ['GET'],
    )]
    public function done(
        string $slug,
        Request $request,
        ServiceRepository $services,
        ServiceRequestRepository $requests,
        ClockInterface $clock,
    ): Response {
        $service = $services->findPublishedBySlug($slug, $request->getLocale(), $clock->now())
            ?? throw $this->createNotFoundException();
        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $service);

        // Sans demande récente dans cette session, la confirmation n'a rien à montrer.
        $number = $request->getSession()->get(self::SESSION_KEY);
        $submitted = \is_string($number) ? $requests->findOneBy(['trackingNumber' => $number]) : null;
        if (null === $submitted || $submitted->getService() !== $service) {
            return $this->redirectToRoute('app_service_show', ['slug' => $service->translate($request->getLocale())->getSlug()]);
        }

        return $this->render('service/request_done.html.twig', [
            'service' => $service,
            'translation' => $service->translate($request->getLocale()),
            'trackingNumber' => $submitted->getTrackingNumber(),
        ]);
    }

    /**
     * @return array{0: Service, 1: ServiceForm}
     */
    private function resolve(string $slug, Request $request, ServiceRepository $services, ServiceFormRegistry $forms, ClockInterface $clock): array
    {
        $service = $services->findPublishedBySlug($slug, $request->getLocale(), $clock->now())
            ?? throw $this->createNotFoundException();
        $definition = $service->hasInternalForm() ? $forms->get($service->getFormKey()) : null;
        if (null === $definition) {
            throw $this->createNotFoundException();
        }

        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $service);

        return [$service, $definition];
    }
}
