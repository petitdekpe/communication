<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\ArticleAdminType;
use App\Admin\Form\ArticleData;
use App\Admin\TranslationApplier;
use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Entity\Media;
use App\Enum\ArticleType;
use App\Security\Permission;
use App\Version\VersionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création et modification d'une actualité, d'un communiqué, d'un dossier ou d'un avis officiel, en deux langues.
 * Éditeur et au-dessus : tous les champs. Traducteur : uniquement l'onglet anglais.
 */
final class ArticleController extends AbstractController
{
    private const array FIELD_NAMES = ['title', 'excerpt', 'body'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationApplier $translations,
        private readonly VersionRecorder $versions,
    ) {
    }

    #[Route('/admin/articles/nouveau', name: 'app_admin_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_CREATE, Article::class);

        $data = new ArticleData();
        $data->type = ArticleType::tryFrom($request->query->getString('type')) ?? ArticleType::Actualite;
        $data->translations = $this->translations->read(null, self::FIELD_NAMES);

        $form = $this->createForm(ArticleAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $article = (new Article())->setType($data->type)->setCategory($data->category)->setFeatured($data->featured)->setCoverImage($this->cover($data));
            $this->translations->apply($article, $data->translations, ['fr', 'en'], ArticleTranslation::class);
            $this->entityManager->persist($article);
            $this->entityManager->flush();
            $this->versions->record($article);
            $this->addFlash('success', 'admin.flash.created');

            return $this->redirectToRoute('app_admin_article_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/article/form.html.twig', $this->context($form, null, $data, $request->getLocale()), $this->status($form));
    }

    #[Route('/admin/articles/{id}', name: 'app_admin_article_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $article = $this->entityManager->find(Article::class, $id) ?? throw $this->createNotFoundException();
        $canEdit = $this->isGranted(Permission::CONTENT_EDIT, $article);
        if (!$canEdit && !$this->isGranted(Permission::CONTENT_TRANSLATE, $article)) {
            throw $this->createAccessDeniedException();
        }

        $data = new ArticleData();
        $data->type = $article->getType();
        $data->category = $article->getCategory();
        $data->featured = $article->isFeatured();
        $data->coverImage = null === $article->getCoverImage() ? null : (string) $article->getCoverImage()->getId();
        $data->translations = $this->translations->read($article, self::FIELD_NAMES);
        $data->editable = $canEdit ? ['fr', 'en'] : ['en'];

        $form = $this->createForm(ArticleAdminType::class, $data, ['details_disabled' => !$canEdit]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($canEdit) {
                $article->setType($data->type)->setCategory($data->category)->setFeatured($data->featured)->setCoverImage($this->cover($data));
            }
            $this->translations->apply($article, $data->translations, $data->editable, ArticleTranslation::class);
            $this->entityManager->flush();
            $this->versions->record($article);
            $this->addFlash('success', 'admin.flash.saved');

            return $this->redirectToRoute('app_admin_article_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/article/form.html.twig', $this->context($form, $article, $data, $request->getLocale()), $this->status($form));
    }

    /** Média de couverture choisi : seule une image existante est acceptée, sinon l'article n'a pas de couverture. */
    private function cover(ArticleData $data): ?Media
    {
        $id = trim((string) $data->coverImage);
        if ('' === $id || !ctype_digit($id)) {
            return null;
        }
        $media = $this->entityManager->find(Media::class, (int) $id);

        return $media instanceof Media && str_starts_with((string) $media->getMimeType(), 'image/') ? $media : null;
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<ArticleData> $form
     *
     * @return array<string, mixed>
     */
    private function context(\Symfony\Component\Form\FormInterface $form, ?Article $article, ArticleData $data, string $locale): array
    {
        return [
            'form' => $form,
            'article' => $article,
            'tabs' => $this->translations->completeness($data->translations, self::FIELD_NAMES),
            'active' => \in_array('fr', $data->editable, true) ? $locale : 'en',
            'translateOnly' => ['en'] === $data->editable,
        ];
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<ArticleData> $form
     */
    private function status(\Symfony\Component\Form\FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
