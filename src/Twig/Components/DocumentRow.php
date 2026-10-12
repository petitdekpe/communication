<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\DocumentType;
use App\Enum\FileType;
use App\Twig\Components\Support\Props;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Ligne de document téléchargeable : icône du type de fichier, titre, poids, date, lien de téléchargement.
 * Détails facultatifs de la bibliothèque : nature du texte, référence officielle, langue du fichier,
 * nombre de téléchargements et résumé.
 */
#[AsTwigComponent]
final class DocumentRow
{
    private const array UNITS = ['byte', 'kb', 'mb', 'gb'];

    public string $title;
    public string $href;
    public FileType $fileType;
    /** Poids en octets. */
    public int $size;
    public \DateTimeInterface $date;
    public ?DocumentType $documentType = null;
    public ?string $reference = null;
    /** Code de langue du fichier (« fr », « en »), affiché par son nom dans la langue de la page. */
    public ?string $language = null;
    public ?int $downloads = null;
    public ?string $summary = null;

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
        Props::enum($resolver, 'fileType', FileType::class);
        $resolver->setRequired('size')->setAllowedTypes('size', 'int')->setAllowedValues('size', static fn (int $size): bool => $size >= 0);
        Props::date($resolver, 'date');
        Props::optionalEnum($resolver, 'documentType', DocumentType::class);
        Props::optionalString($resolver, 'reference', 'language', 'summary');
        $resolver->setDefault('downloads', null)->setAllowedTypes('downloads', ['null', 'int']);

        return Props::resolve($resolver, $data);
    }

    /**
     * Poids lisible : valeur et clé de traduction de l'unité (formatage localisé dans la traduction ICU).
     *
     * @return array{value: float|int, unitKey: string}
     */
    public function getReadableSize(): array
    {
        $value = $this->size;
        $unit = 0;
        while ($value >= 1024 && $unit < \count(self::UNITS) - 1) {
            $value /= 1024;
            ++$unit;
        }

        return ['value' => 0 === $unit ? $value : round($value, 1), 'unitKey' => 'file_size.'.self::UNITS[$unit]];
    }
}
