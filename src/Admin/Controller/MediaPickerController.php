<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Entity\Media;
use App\Entity\MediaTranslation;
use App\Media\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sélecteur de médias de l'éditeur de page : liste des images de la médiathèque (recherche, pagination) affichée dans
 * une fenêtre, et envoi d'une nouvelle image avec son texte alternatif. Le texte alternatif français est obligatoire.
 */
final class MediaPickerController extends AbstractController
{
    public const string UPLOAD_TOKEN = 'media_picker_upload';

    private const int PER_PAGE = 24;
    private const int MAX_SIZE = 5_000_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaStorage $storage,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly CacheManager $imagine,
    ) {
    }

    #[Route('/admin/medias/selecteur', name: 'app_admin_media_picker', methods: ['GET'])]
    public function picker(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $query = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));

        $builder = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(Media::class, 'm')
            ->where('m.mimeType LIKE :image')
            ->setParameter('image', 'image/%')
            ->orderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE + 1);
        if ('' !== $query) {
            $builder
                ->leftJoin('m.translations', 't')
                ->andWhere('m.originalFilename LIKE :q OR t.alt LIKE :q')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }

        /** @var list<Media> $medias */
        $medias = $builder->getQuery()->getResult();
        $more = \count($medias) > self::PER_PAGE;

        return $this->render('admin/media/_picker.html.twig', [
            'medias' => \array_slice($medias, 0, self::PER_PAGE),
            'query' => $query,
            'page' => $page,
            'more' => $more,
        ]);
    }

    #[Route('/admin/medias/televerser', name: 'app_admin_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (!$this->isCsrfTokenValid(self::UPLOAD_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('file');
        $altFr = trim($request->request->getString('alt_fr'));
        $altEn = trim($request->request->getString('alt_en'));
        if (!$file instanceof UploadedFile) {
            return $this->error('page_editor.media.error.file');
        }
        if ('' === $altFr) {
            return $this->error('page_editor.media.error.alt');
        }

        $problems = $this->validator->validate($file, new Assert\Image(
            maxSize: self::MAX_SIZE,
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ));
        if (\count($problems) > 0) {
            return new JsonResponse(['error' => (string) $problems[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $media = $this->storage->store($file);
        $media->addTranslation((new MediaTranslation())->setLocale('fr')->setAlt(mb_substr($altFr, 0, 255)));
        if ('' !== $altEn) {
            $media->addTranslation((new MediaTranslation())->setLocale('en')->setAlt(mb_substr($altEn, 0, 255)));
        }
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $media->getId(),
            'name' => $media->getOriginalFilename(),
            'thumb' => $this->imagine->getBrowserPath((string) $media->getPath(), 'thumb'),
        ], Response::HTTP_CREATED);
    }

    private function error(string $key): JsonResponse
    {
        return new JsonResponse(['error' => $this->translator->trans($key)], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
