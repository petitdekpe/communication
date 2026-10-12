<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminResources;
use App\Admin\Export\ResourceCsvExporter;
use App\Security\Permission;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Export CSV d'une liste de « demandes reçues » sur une période (paramètres from et to, au format AAAA-MM-JJ).
 * Chaque export est journalisé (données personnelles), avec l'identifiant de l'utilisateur et sans le contenu.
 */
final class ExportController extends AbstractController
{
    #[Route('/admin/liste/{resource}/export.csv', name: 'app_admin_resource_export', requirements: ['resource' => '[a-z-]+'], methods: ['GET'])]
    public function export(string $resource, Request $request, AdminResources $resources, ResourceCsvExporter $exporter, LoggerInterface $logger): Response
    {
        $definition = $resources->get($resource);
        if (null === $definition || !$definition->exportable || !$exporter->supports($definition)) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(Permission::RESOURCE_VIEW, $definition);

        $from = $this->date($request->query->getString('from'));
        $to = $this->date($request->query->getString('to'));
        if (false === $from || false === $to || (null !== $from && null !== $to && $to < $from)) {
            throw $this->createNotFoundException();
        }

        $logger->info('Export CSV « {resource} » par {user} ({from} → {to}).', [
            'resource' => $definition->id,
            'user' => $this->getUser()?->getUserIdentifier(),
            'from' => $from?->format('Y-m-d') ?? '…',
            'to' => $to?->format('Y-m-d') ?? '…',
        ]);

        $response = new StreamedResponse(static function () use ($exporter, $definition, $from, $to): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            $exporter->write($definition, $from, $to, $out);
            fclose($out);
        });
        $name = \sprintf('%s-%s-%s.csv', $definition->id, $from?->format('Y-m-d') ?? 'debut', $to?->format('Y-m-d') ?? 'fin');
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return \DateTimeImmutable|false|null null si vide, false si invalide
     */
    private function date(string $value): \DateTimeImmutable|false|null
    {
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : false;
    }
}
