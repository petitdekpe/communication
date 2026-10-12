<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie du formulaire d'une page : titre, texte et métadonnées de référencement dans chaque langue.
 */
final class PageData
{
    /** @var array<string, array<string, string|null>> */
    public array $translations = [];

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $context): void
    {
        foreach (['fr', 'en'] as $locale) {
            $values = $this->translations[$locale] ?? [];
            $title = trim((string) ($values['title'] ?? ''));
            $others = array_filter($values, static fn (?string $v, string $k): bool => 'title' !== $k && '' !== trim((string) $v), \ARRAY_FILTER_USE_BOTH);
            // Le français est la langue de référence : son titre est toujours obligatoire.
            if ('' === $title && ('fr' === $locale || [] !== $others)) {
                $context->buildViolation('request.error.required')->atPath(\sprintf('translations[%s][title]', $locale))->addViolation();
            }
            if (mb_strlen($title) > 255) {
                $context->buildViolation('admin.error.too_long')->atPath(\sprintf('translations[%s][title]', $locale))->addViolation();
            }
        }
    }
}
