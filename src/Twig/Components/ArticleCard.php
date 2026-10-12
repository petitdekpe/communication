<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\ContentCategory;
use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Carte d'actualité : image 16:9, badge de catégorie, date, titre (Baskervville), chapô.
 * Toute la carte est cliquable (lien étendu sur le titre).
 */
#[AsTwigComponent]
final class ArticleCard
{
    public string $title;
    public string $href;
    public \DateTimeInterface $date;
    public ?ContentCategory $category = null;
    public ?string $excerpt = null;
    /** Chemin AssetMapper (images/…) ou URL absolue. */
    public ?string $image = null;
    /** Texte alternatif : obligatoire si une image est fournie ('' seulement si l'image est purement décorative). */
    public ?string $imageAlt = null;
    public int $headingLevel = 3;
    /** Mise en avant (« À la une ») : titre et chapô agrandis, image chargée immédiatement. */
    public bool $featured = false;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        Props::requiredString($resolver, 'title', 'href');
        Props::date($resolver, 'date');
        Props::optionalString($resolver, 'excerpt', 'image');
        $resolver->setDefault('imageAlt', null)->setAllowedTypes('imageAlt', ['null', 'string']);
        Props::optionalEnum($resolver, 'category', ContentCategory::class);
        Props::headingLevel($resolver, 3);
        Props::bool($resolver, 'featured', false);

        $resolved = Props::resolve($resolver, $data);
        Props::assertPaired($resolved, 'image', 'imageAlt');

        return $resolved;
    }
}
