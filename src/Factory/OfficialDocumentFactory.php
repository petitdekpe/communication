<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Contract\TranslationInterface;
use App\Entity\OfficialDocument;
use App\Entity\OfficialDocumentTranslation;
use App\Enum\DocumentType;
use App\Enum\PublicationStatus;

/**
 * @extends TranslatableFactory<OfficialDocument>
 */
final class OfficialDocumentFactory extends TranslatableFactory
{
    public static function class(): string
    {
        return OfficialDocument::class;
    }

    protected function defaults(): array
    {
        return [
            'reference' => self::MARKER,
            'type' => DocumentType::Report,
            'fileLanguage' => 'fr',
            'documentDate' => self::pastDate('-2 years'),
            'status' => PublicationStatus::Published,
            'publishedAt' => self::pastDate(),
            'file' => MediaFactory::new()->pdf(),
        ];
    }

    protected function translation(string $locale, int $number): TranslationInterface
    {
        return (new OfficialDocumentTranslation())
            ->setLocale($locale)
            ->setTitle(self::text($locale, 'Intitulé du document officiel', 'Official document title', $number))
            ->setSummary(self::text($locale, 'Résumé du document', 'Document summary'));
    }
}
