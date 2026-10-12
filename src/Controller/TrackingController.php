<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ServiceRequestRepository;
use App\ServiceRequest\RequestRateLimiter;
use App\ServiceRequest\TrackingLookup;
use App\ServiceRequest\TrackingLookupType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Suivi d'une demande avec le numéro de suivi et l'e-mail. Les deux valeurs voyagent dans le corps d'un POST,
 * jamais dans l'URL ; le résultat est affiché dans la réponse à ce POST (une requête GET ne fait aucune recherche).
 */
final class TrackingController extends AbstractController
{
    #[Route(
        path: ['fr' => '/suivi', 'en' => '/tracking'],
        name: 'app_tracking',
        defaults: ['_sensitive' => true],
        methods: ['GET', 'POST'],
    )]
    public function track(
        Request $request,
        ServiceRequestRepository $requests,
        RequestRateLimiter $limiter,
        TranslatorInterface $translator,
    ): Response {
        $lookup = new TrackingLookup();
        $form = $this->createForm(TrackingLookupType::class, $lookup);
        $form->handleRequest($request);

        $found = null;
        $status = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            if (!$limiter->consumeTracking($request)->isAccepted()) {
                $form->addError(new FormError($translator->trans('request.error.too_many')));
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($form->isValid()) {
                $found = $requests->findForTracking((string) $lookup->number, (string) $lookup->email);
                if (null === $found) {
                    // Même message que le numéro soit inconnu ou l'e-mail erroné : rien n'est révélé sur l'existence d'une demande.
                    $form->addError(new FormError($translator->trans('tracking.error.not_found')));
                }
            }
            if (null === $found && Response::HTTP_OK === $status) {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        }

        return $this->render('tracking/index.html.twig', [
            'form' => $form,
            'found' => $found,
        ], new Response(status: $status));
    }
}
