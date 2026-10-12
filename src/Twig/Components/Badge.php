<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\Audience;
use App\Enum\ContentCategory;
use App\Twig\Components\Support\Props;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Étiquette de catégorie éditoriale (pleine) ou de public visé (contour).
 *
 *   <twig:Badge value="communique" />   <twig:Badge value="journalistes" />   <twig:Badge :value="article.category" />
 */
#[AsTwigComponent]
final class Badge
{
    public ContentCategory|Audience $value;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        $resolver
            ->setRequired('value')
            ->setAllowedTypes('value', ['string', ContentCategory::class, Audience::class])
            ->setNormalizer('value', static function (Options $o, string|ContentCategory|Audience $value): ContentCategory|Audience {
                if (!\is_string($value)) {
                    return $value;
                }

                return ContentCategory::tryFrom($value) ?? Audience::tryFrom($value) ?? throw new InvalidOptionsException(\sprintf('La prop "value" attend une catégorie (%s) ou un public (%s) ; "%s" reçu.', implode(', ', array_column(ContentCategory::cases(), 'value')), implode(', ', array_column(Audience::cases(), 'value')), $value));
            });

        return Props::resolve($resolver, $data);
    }

    public function isAudience(): bool
    {
        return $this->value instanceof Audience;
    }

    public function getCssClass(): string
    {
        return match ($this->value) {
            ContentCategory::Actualite => 'bg-primary-light text-white',
            ContentCategory::Communique => 'bg-primary text-white',
            ContentCategory::Dossier => 'bg-cream text-primary',
            ContentCategory::Annonce => 'bg-flag-green text-white',
            ContentCategory::AvisOfficiel => 'bg-flag-red text-white',
            Audience::Journalistes, Audience::Medias, Audience::Entreprises, Audience::Citoyens => 'bg-white text-primary ring-1 ring-primary ring-inset',
        };
    }
}
