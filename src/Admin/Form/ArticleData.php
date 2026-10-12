<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\Category;
use App\Enum\ArticleType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie du formulaire d'un article (actualité, communiqué, dossier, avis officiel).
 */
final class ArticleData
{
    public ArticleType $type = ArticleType::Actualite;

    public ?Category $category = null;

    public bool $featured = false;

    /** Identifiant du média de couverture (champ caché rempli par le sélecteur de médias), vide = pas d'image. */
    public ?string $coverImage = null;

    /** @var array<string, array<string, string|null>> */
    public array $translations = [];

    /**
     * Langues modifiables par l'utilisateur (le traducteur ne modifie que « en »).
     *
     * @var list<string>
     */
    public array $editable = ['fr', 'en'];

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $context): void
    {
        foreach ($this->editable as $locale) {
            $values = $this->translations[$locale] ?? [];
            $title = trim((string) ($values['title'] ?? ''));
            $others = array_filter($values, static fn (?string $v, string $k): bool => 'title' !== $k && '' !== trim((string) $v), \ARRAY_FILTER_USE_BOTH);
            // Le français est la langue de référence : son titre est toujours obligatoire (à la création).
            if ('' === $title && ('fr' === $locale || [] !== $others)) {
                $context->buildViolation('request.error.required')->atPath(\sprintf('translations[%s][title]', $locale))->addViolation();
            }
            if (mb_strlen($title) > 255) {
                $context->buildViolation('admin.error.too_long')->atPath(\sprintf('translations[%s][title]', $locale))->addViolation();
            }
        }
    }
}
