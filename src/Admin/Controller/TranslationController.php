<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Security\Permission;
use App\Translation\TranslationDashboard;
use App\Translation\XliffExporter;
use App\Translation\XliffImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord des traductions : contenus publiés sans version anglaise, export XLIFF (pour un traducteur ou un outil
 * de gestion de traduction) et import XLIFF en deux temps (aperçu des modifications, puis validation).
 */
final class TranslationController extends AbstractController
{
    private const string SESSION_KEY = 'translation_import';

    #[Route('/admin/traductions', name: 'app_admin_translations', methods: ['GET'])]
    public function index(TranslationDashboard $dashboard): Response
    {
        $this->denyAccessUnlessGranted(Permission::TRANSLATION_MANAGE);

        return $this->render('admin/translations/index.html.twig', ['groups' => $dashboard->missing(), 'limit' => TranslationDashboard::LIMIT]);
    }

    #[Route('/admin/traductions/export.xlf', name: 'app_admin_translations_export', methods: ['GET'])]
    public function export(Request $request, TranslationDashboard $dashboard, XliffExporter $exporter): Response
    {
        $this->denyAccessUnlessGranted(Permission::TRANSLATION_MANAGE);

        $only = $request->query->getString('resource');
        $contents = [];
        foreach ($dashboard->missing('' === $only ? null : $only) as $group) {
            foreach ($group['items'] as $item) {
                if ($this->isGranted(Permission::CONTENT_TRANSLATE, $item['entity'])) {
                    $contents[] = ['resource' => $group['resource'], 'id' => $item['id'], 'entity' => $item['entity']];
                }
            }
        }

        $response = new Response($exporter->export($contents));
        $response->headers->set('Content-Type', 'application/xliff+xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('traductions-fr-en%s.xlf', '' === $only ? '' : '-'.preg_replace('/[^a-z-]/', '', $only))));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** Aperçu : le fichier est analysé, rien n'est écrit. Le fichier est gardé en session pour la validation. */
    #[Route('/admin/traductions/import', name: 'app_admin_translations_import', methods: ['POST'])]
    public function import(Request $request, XliffImporter $importer): Response
    {
        $this->denyAccessUnlessGranted(Permission::TRANSLATION_MANAGE);
        if (!$this->isCsrfTokenValid('admin_translations_import', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('xliff');
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > XliffImporter::MAX_BYTES) {
            $this->addFlash('error', 'admin.translations.error.file');

            return $this->redirectToRoute('app_admin_translations', [], Response::HTTP_SEE_OTHER);
        }

        $xml = (string) file_get_contents($file->getPathname());
        $preview = $importer->preview($xml);

        $token = null;
        if ($preview->hasApplicable()) {
            $token = bin2hex(random_bytes(16));
            $request->getSession()->set(self::SESSION_KEY, ['id' => $token, 'xml' => $xml]);
        }

        return $this->render('admin/translations/preview.html.twig', ['preview' => $preview, 'importId' => $token]);
    }

    #[Route('/admin/traductions/import/valider', name: 'app_admin_translations_confirm', methods: ['POST'])]
    public function confirm(Request $request, XliffImporter $importer): Response
    {
        $this->denyAccessUnlessGranted(Permission::TRANSLATION_MANAGE);
        if (!$this->isCsrfTokenValid('admin_translations_confirm', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $pending = $session->get(self::SESSION_KEY);
        $session->remove(self::SESSION_KEY);
        if (!\is_array($pending) || !\is_string($pending['xml'] ?? null) || !hash_equals((string) ($pending['id'] ?? ''), $request->request->getString('id'))) {
            $this->addFlash('error', 'admin.translations.error.expired');

            return $this->redirectToRoute('app_admin_translations', [], Response::HTTP_SEE_OTHER);
        }

        $count = $importer->apply($pending['xml']);
        $this->addFlash($count > 0 ? 'success' : 'warning', $count > 0 ? 'admin.translations.flash.imported' : 'admin.translations.flash.nothing');

        return $this->redirectToRoute('app_admin_translations', [], Response::HTTP_SEE_OTHER);
    }
}
