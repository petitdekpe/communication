<?php

declare(strict_types=1);

namespace App\Version;

use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Instantané JSON d'un contenu éditorial (champs, associations par identifiant, traductions) et restauration.
 * Le statut et les dates du circuit de publication ne font pas partie de l'instantané : restaurer une version
 * ne change jamais l'état de publication.
 *
 * Format : ['fields' => [champ => valeur], 'translations' => [langue => [champ => valeur]]].
 * Les dates sont en ISO 8601, les énumérations par leur valeur, les associations par identifiant.
 */
final class ContentSnapshotter
{
    private const array ROOT_EXCLUDED = ['id', 'status', 'publishedAt', 'scheduledAt', 'createdAt', 'updatedAt', 'createdBy', 'updatedBy', 'systemKey'];
    private const array TRANSLATION_EXCLUDED = ['id', 'locale', 'slug', 'translatable'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>}
     */
    public function snapshot(PublishableInterface $content): array
    {
        $translations = [];
        if ($content instanceof TranslatableInterface) {
            foreach ($content->getTranslations() as $translation) {
                $translations[$translation->getLocale()] = $this->read($translation, self::TRANSLATION_EXCLUDED);
            }
            ksort($translations);
        }

        return ['fields' => $this->read($content, self::ROOT_EXCLUDED), 'translations' => $translations];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function restore(PublishableInterface $content, array $snapshot): void
    {
        $fields = $snapshot['fields'] ?? [];
        $this->write($content, \is_array($fields) ? $fields : [], self::ROOT_EXCLUDED);

        if (!$content instanceof TranslatableInterface) {
            return;
        }
        $translations = $snapshot['translations'] ?? [];
        $translations = \is_array($translations) ? $translations : [];
        $class = $this->entityManager->getClassMetadata($content::class)->getAssociationTargetClass('translations');
        foreach ($translations as $locale => $values) {
            $translation = $content->getTranslation((string) $locale);
            if (null === $translation) {
                $translation = new $class();
                \assert($translation instanceof TranslationInterface);
                $translation->setLocale((string) $locale);
                $content->addTranslation($translation);
            }
            $this->write($translation, \is_array($values) ? $values : [], self::TRANSLATION_EXCLUDED);
        }
        foreach ($content->getTranslations() as $translation) {
            if (!isset($translations[$translation->getLocale()])) {
                $content->removeTranslation($translation);
            }
        }
    }

    /**
     * @param list<string> $excluded
     *
     * @return array<string, mixed>
     */
    private function read(object $entity, array $excluded): array
    {
        $metadata = $this->entityManager->getClassMetadata($entity::class);
        $values = [];

        foreach ($metadata->getFieldNames() as $name) {
            if (!\in_array($name, $excluded, true)) {
                $values[$name] = $this->normalize($metadata->getFieldValue($entity, $name));
            }
        }
        foreach ($metadata->getAssociationNames() as $name) {
            $mapping = $metadata->getAssociationMapping($name);
            if (\in_array($name, $excluded, true) || 'translations' === $name || !$mapping->isOwningSide()) {
                continue;
            }
            $value = $metadata->getFieldValue($entity, $name);
            if ($value instanceof Collection) {
                $ids = array_map(fn (object $related): mixed => $this->identifier($related), $value->toArray());
                sort($ids);
                $values[$name] = $ids;
            } else {
                $values[$name] = \is_object($value) ? $this->identifier($value) : null;
            }
        }
        ksort($values);

        return $values;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $excluded
     */
    private function write(object $entity, array $values, array $excluded): void
    {
        $metadata = $this->entityManager->getClassMetadata($entity::class);

        foreach ($values as $name => $value) {
            $name = (string) $name;
            if (\in_array($name, $excluded, true)) {
                continue;
            }
            if ($metadata->hasField($name)) {
                $metadata->setFieldValue($entity, $name, $this->denormalize($metadata, $name, $value));
            } elseif ($metadata->hasAssociation($name)) {
                $this->writeAssociation($metadata, $entity, $name, $value);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function writeAssociation(ClassMetadata $metadata, object $entity, string $name, mixed $value): void
    {
        $target = $metadata->getAssociationTargetClass($name);

        if ($metadata->isSingleValuedAssociation($name)) {
            $related = \is_int($value) || \is_string($value) ? $this->entityManager->find($target, $value) : null;
            $metadata->setFieldValue($entity, $name, $related);

            return;
        }

        $collection = $metadata->getFieldValue($entity, $name);
        if ($collection instanceof Collection && \is_array($value)) {
            $collection->clear();
            foreach ($value as $id) {
                $related = $this->entityManager->find($target, $id);
                if (null !== $related) {
                    $collection->add($related);
                }
            }
        }
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \BackedEnum => $value->value,
            default => $value,
        };
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function denormalize(ClassMetadata $metadata, string $name, mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }
        $enum = $metadata->getFieldMapping($name)->enumType ?? null;
        if (null !== $enum && (\is_int($value) || \is_string($value))) {
            return $enum::from($value);
        }
        if (\is_string($value)) {
            return match ($metadata->getTypeOfField($name)) {
                'datetime_immutable', 'date_immutable', 'time_immutable' => new \DateTimeImmutable($value),
                'datetime', 'date', 'time' => new \DateTime($value),
                default => $value,
            };
        }

        return $value;
    }

    private function identifier(object $entity): mixed
    {
        $ids = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);

        return 1 === \count($ids) ? reset($ids) : null;
    }
}
