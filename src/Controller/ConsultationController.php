<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consultation\ContributionData;
use App\Consultation\ContributionSubmitter;
use App\Consultation\ContributionType;
use App\Entity\Consultation;
use App\Repository\ConsultationRepository;
use App\Routing\AlternateUrlResolver;
use App\ServiceRequest\RequestRateLimiter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Consultations citoyennes : liste, fiche avec formulaire de contribution, page de résultats (publiée par l'administration).
 */
final class ConsultationController extends AbstractController
{
    #[Route(path: ['fr' => '/consultations', 'en' => '/consultations'], name: 'app_consultations', methods: ['GET'])]
    public function index(ConsultationRepository $consultations, ClockInterface $clock): Response
    {
        $now = $clock->now();

        return $this->render('consultation/index.html.twig', [
            'consultations' => $consultations->findPublished($now),
            'now' => $now,
        ]);
    }

    #[Route(
        path: ['fr' => '/consultations/{slug}', 'en' => '/consultations/{slug}'],
        name: 'app_consultation_show',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET', 'POST'],
    )]
    public function show(
        string $slug,
        Request $request,
        ConsultationRepository $consultations,
        ContributionSubmitter $submitter,
        RequestRateLimiter $limiter,
        TranslatorInterface $translator,
        ClockInterface $clock,
    ): Response {
        $now = $clock->now();
        $consultation = $this->find($slug, $request, $consultations, $now);
        $translation = $consultation->translate($request->getLocale());
        if ($request->isMethod(Request::METHOD_GET) && $translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_consultation_show', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $open = $consultation->isOpen($now);
        $form = null;
        if ($open) {
            $data = new ContributionData();
            $form = $this->createForm(ContributionType::class, $data);
            $form->handleRequest($request);

            if ($request->isMethod(Request::METHOD_POST)) {
                if (!$limiter->consumeContribution($request)->isAccepted()) {
                    $form->addError(new FormError($translator->trans('request.error.too_many')));
                } elseif (!$form->isSubmitted()) {
                    $form->addError(new FormError($translator->trans('request.error.too_large')));
                }

                if ($form->isSubmitted() && $form->isValid()) {
                    $submitter->submit($consultation, $data);
                    $this->addFlash('contribution', 'consultation.contribute.thanks');

                    return $this->redirectToRoute('app_consultation_show', ['slug' => $translation->getSlug()], Response::HTTP_SEE_OTHER);
                }
            }
        } elseif ($request->isMethod(Request::METHOD_POST)) {
            throw new AccessDeniedHttpException();
        }

        return $this->render('consultation/show.html.twig', [
            'consultation' => $consultation,
            'translation' => $translation,
            'open' => $open,
            'upcoming' => null !== $consultation->getOpensAt() && $now < $consultation->getOpensAt(),
            'form' => $form,
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(
        path: ['fr' => '/consultations/{slug}/resultats', 'en' => '/consultations/{slug}/results'],
        name: 'app_consultation_results',
        requirements: ['slug' => Requirement::ASCII_SLUG],
        methods: ['GET'],
    )]
    public function results(string $slug, Request $request, ConsultationRepository $consultations, ClockInterface $clock): Response
    {
        $consultation = $this->find($slug, $request, $consultations, $clock->now());
        $translation = $consultation->translate($request->getLocale());
        // Page invisible tant que l'administration ne l'a pas publiée, et sans texte de résultats.
        if (!$consultation->isResultsPublished() || '' === trim((string) $translation->getResults())) {
            throw $this->createNotFoundException();
        }
        if ($translation->getSlug() !== $slug) {
            return $this->redirectToRoute('app_consultation_results', ['slug' => $translation->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('consultation/results.html.twig', [
            'consultation' => $consultation,
            'translation' => $translation,
        ]);
    }

    private function find(string $slug, Request $request, ConsultationRepository $consultations, \DateTimeImmutable $now): Consultation
    {
        $consultation = $consultations->findPublishedBySlug($slug, $request->getLocale(), $now)
            ?? throw $this->createNotFoundException();
        $request->attributes->set(AlternateUrlResolver::TRANSLATABLE_ATTRIBUTE, $consultation);

        return $consultation;
    }
}
