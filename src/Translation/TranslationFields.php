<?php

declare(strict_types=1);

namespace App\Translation;

use App\Admin\AdminResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Champs textuels traduisibles d'une ressource (colonnes texte de son entité de traduction, hors identifiants,
 * langue et slug), avec leurs contraintes de base (obligatoire, longueur maximale). Source commune de l'export
 * et de l'import XLIFF : seuls ces champs peuvent être écrits par un fichier importé.
 */
final class TranslationFields
{
    private const array EXCLUDED = ['id', 'locale', 'slug'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<string>
     */
    public function for(AdminResource $resource): array
    {
        $class = $resource->translationClass();
        if (null === $class) {
            return [];
        }

        $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($class);
        $fields = [];
        foreach ($metadata->getFieldNames() as $field) {
            if (!\in_array($field, self::EXCLUDED, true) && \in_array($metadata->getTypeOfField($field), [Types::STRING, Types::TEXT], true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Champs obligatoires en base : une traduction ne peut être créée sans eux.
     *
     * @return list<string>
     */
    public function required(AdminResource $resource): array
    {
        $class = $resource->translationClass();
        if (null === $class) {
            return [];
        }
        $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($class);

        return array_values(array_filter($this->for($resource), static fn (string $field): bool => !$metadata->isNullable($field)));
    }

    public function maxLength(AdminResource $resource, string $field): ?int
    {
        $class = $resource->translationClass();
        if (null === $class) {
            return null;
        }
        $length = $this->entityManager->getMetadataFactory()->getMetadataFor($class)->getFieldMapping($field)->length;

        return \is_int($length) ? $length : null;
    }
}
