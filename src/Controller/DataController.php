<?php

declare(strict_types=1);

namespace App\Controller;

use App\Data\KeyFigureCsvExporter;
use App\Repository\KeyFigureRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Chiffres clés sourcés, avec export CSV.
 */
final class DataController extends AbstractController
{
    #[Route(path: ['fr' => '/donnees', 'en' => '/data'], name: 'app_data', methods: ['GET'])]
    public function index(KeyFigureRepository $figures, ClockInterface $clock): Response
    {
        return $this->render('data/index.html.twig', ['figures' => $figures->findPublished($clock->now())]);
    }

    #[Route(path: ['fr' => '/donnees/export.csv', 'en' => '/data/export.csv'], name: 'app_data_export', methods: ['GET'])]
    public function export(Request $request, KeyFigureRepository $figures, KeyFigureCsvExporter $exporter, ClockInterface $clock): Response
    {
        $rows = $figures->findPublished($clock->now());
        $locale = $request->getLocale();

        $response = new StreamedResponse(static function () use ($exporter, $rows, $locale): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            $exporter->write($rows, $locale, $out);
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('chiffres-cles-%s.csv', $locale)));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
