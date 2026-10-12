<?php

declare(strict_types=1);

namespace App\Preview;

use App\Entity\Article;
use App\Entity\OfficialDocument;

/**
 * Choisit le gabarit public et les variables d'aperçu d'un contenu non publié (ressources : actualités, documents).
 */
final class PreviewRenderer
{
    public const array RESOURCES = ['articles', 'documents'];

    /**
     * @return array{template: string, context: array<string, mixed>}|null
     */
    public function render(object $content, string $locale): ?array
    {
        if ($content instanceof Article) {
            return ['template' => 'article/show.html.twig', 'context' => [
                'article' => $content,
                'translation' => $content->translate($locale),
                'related' => [],
                'attachments' => $content->getPublishedAttachments(),
                'preview' => true,
            ]];
        }

        if ($content instanceof OfficialDocument) {
            return ['template' => 'preview/document.html.twig', 'context' => [
                'document' => $content,
                'translation' => $content->translate($locale),
                'preview' => true,
            ]];
        }

        return null;
    }
}
