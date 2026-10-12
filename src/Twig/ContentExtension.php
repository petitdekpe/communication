<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Article;
use App\Entity\Media;
use App\Enum\FileType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonctions Twig des contenus : URL d'un article et d'un fichier, texte alternatif d'un média.
 */
final class ContentExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** URL de l'article dans la langue de la page (slug français en cas de repli). */
    #[AsTwigFunction('article_url')]
    public function articleUrl(Article $article, bool $absolute = false): string
    {
        return $this->urlGenerator->generate(
            'app_article_show',
            ['slug' => $article->translate($this->locale())->getSlug()],
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    #[AsTwigFunction('media_url')]
    public function mediaUrl(Media $media, bool $absolute = false): string
    {
        return $this->urlGenerator->generate(
            'app_media',
            ['id' => $media->getId(), 'filename' => basename((string) $media->getPath())],
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    /** Texte alternatif dans la langue de la page ('' si le média est décoratif ou non renseigné). */
    #[AsTwigFunction('media_alt')]
    public function mediaAlt(Media $media): string
    {
        $translation = $media->translate($this->locale());

        return (string) $translation->getAlt();
    }

    #[AsTwigFunction('enum_file_type')]
    public function fileType(?string $mimeType): FileType
    {
        return FileType::fromMimeType($mimeType);
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }
}
