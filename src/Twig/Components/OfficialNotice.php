<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Bandeau d'avis officiel. S'il est fermable, la fermeture est mémorisée dans le localStorage
 * du visiteur sous la clé « mcm:avis:{id} » : changer l'id republie l'avis pour tout le monde.
 */
#[AsTwigComponent]
final class OfficialNotice
{
    /** Identifiant stable de l'avis (minuscules, chiffres, tirets). */
    public string $id;
    public string $message;
    /** Intitulé en gras ; par défaut « Avis officiel » traduit. */
    public ?string $title = null;
    public ?string $href = null;
    public ?string $linkLabel = null;
    public bool $dismissible = true;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = Props::resolver();
        Props::requiredString($resolver, 'id', 'message');
        $resolver->setAllowedValues('id', static fn (string $id): bool => 1 === preg_match('/^[a-z0-9-]+$/', $id));
        Props::optionalString($resolver, 'title', 'href', 'linkLabel');
        Props::bool($resolver, 'dismissible', true);

        return Props::resolve($resolver, $data);
    }
}
