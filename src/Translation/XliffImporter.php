<?php

declare(strict_types=1);

namespace App\Translation;

use App\Admin\AdminResource;
use App\Admin\AdminResources;
use App\Admin\TranslationApplier;
use App\Entity\Contract\TranslatableInterface;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Import XLIFF 1.2 des traductions anglaises, en deux temps : preview() analyse le fichier sans rien écrire, apply()
 * réanalyse le même fichier puis écrit les lignes valides. Garde-fous : le fichier ne définit pas d'entités XML (DOCTYPE
 * refusé), seuls les champs texte de la traduction anglaise peuvent être écrits, la longueur des colonnes et les champs
 * obligatoires sont vérifiés, et chaque contenu doit être modifiable par l'utilisateur (droit de traduction).
 */
final class XliffImporter
{
    public const int MAX_BYTES = 2_000_000;

    private readonly PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly AdminResources $resources,
        private readonly TranslationFields $fields,
        private readonly TranslationDashboard $dashboard,
        private readonly TranslationApplier $applier,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function preview(string $xml): ImportPreview
    {
        if (\strlen($xml) > self::MAX_BYTES) {
            return new ImportPreview(fileErrors: ['admin.translations.error.too_large']);
        }
        if (false !== stripos($xml, '<!DOCTYPE') || false !== stripos($xml, '<!ENTITY')) {
            return new ImportPreview(fileErrors: ['admin.translations.error.invalid']);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml, \LIBXML_NONET | \LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || 'xliff' !== $document->documentElement?->localName || XliffExporter::NAMESPACE !== $document->documentElement->namespaceURI) {
            return new ImportPreview(fileErrors: ['admin.translations.error.invalid']);
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', XliffExporter::NAMESPACE);

        $rows = [];
        /** @var \DOMNodeList<\DOMElement> $files */
        $files = $xpath->query('/x:xliff/x:file') ?: [];
        foreach ($files as $file) {
            array_push($rows, ...$this->fileRows($file, $xpath));
        }

        return [] === $rows ? new ImportPreview(fileErrors: ['admin.translations.error.empty']) : new ImportPreview($rows);
    }

    /**
     * Écrit les lignes valides du fichier. Retourne le nombre de champs modifiés.
     */
    public function apply(string $xml): int
    {
        $byContent = [];
        foreach ($this->preview($xml)->applicable() as $row) {
            $byContent[$row->resourceId.'/'.$row->entityId][] = $row;
        }

        $count = 0;
        foreach ($byContent as $rows) {
            $resource = $this->resources->get($rows[0]->resourceId);
            $class = $resource?->translationClass();
            $entity = null === $resource ? null : $this->entityManager->find($resource->entity, $rows[0]->entityId);
            if (null === $class || !$entity instanceof TranslatableInterface) {
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $values[$row->field] = $row->proposed;
            }
            $this->applier->apply($entity, ['en' => $values], ['en'], $class);
            $count += \count($values);
        }
        $this->entityManager->flush();

        return $count;
    }

    /**
     * @return list<ImportRow>
     */
    private function fileRows(\DOMElement $file, \DOMXPath $xpath): array
    {
        if (1 !== preg_match('/^([a-z-]+)\/(\d+)$/', $file->getAttribute('original'), $m)) {
            return [];
        }
        $resource = $this->resources->get($m[1]);
        if (!$resource instanceof AdminResource || !$resource->workflow || !$resource->translatable || !str_starts_with(strtolower($file->getAttribute('target-language')), 'en')) {
            return [];
        }
        $entity = $this->entityManager->find($resource->entity, (int) $m[2]);
        if (!$entity instanceof TranslatableInterface) {
            return [];
        }

        $allowed = $this->fields->for($resource);
        $english = $entity->getTranslation('en');
        $title = $this->title($resource, $entity);
        $rows = [];
        /** @var \DOMNodeList<\DOMElement> $units */
        $units = $xpath->query('x:body/x:trans-unit', $file) ?: [];
        foreach ($units as $unit) {
            $field = $unit->getAttribute('id');
            $target = trim((string) $xpath->evaluate('string(x:target)', $unit));
            if ('' === $target || !\in_array($field, $allowed, true)) {
                continue; // champ inconnu ou pas encore traduit : ignoré
            }
            $current = null === $english ? null : $this->accessor->getValue($english, $field);
            $current = \is_string($current) ? $current : null;
            if ($current === $target) {
                continue; // inchangé
            }

            $row = new ImportRow($resource->id, (int) $m[2], $title, $field, $current, $target);
            $max = $this->fields->maxLength($resource, $field);
            if (!$this->security->isGranted(Permission::CONTENT_TRANSLATE, $entity)) {
                $row = $row->withError('admin.translations.error.forbidden');
            } elseif (null !== $max && mb_strlen($target) > $max) {
                $row = $row->withError('admin.translations.error.too_long');
            }
            $rows[] = $row;
        }

        // Une traduction anglaise nouvelle doit couvrir les champs obligatoires (titre…).
        if (null === $english) {
            $provided = array_map(static fn (ImportRow $row): string => $row->field, array_filter($rows, static fn (ImportRow $row): bool => null === $row->error));
            if ([] !== $provided && [] !== array_diff($this->fields->required($resource), $provided)) {
                $rows = array_map(static fn (ImportRow $row): ImportRow => null === $row->error ? $row->withError('admin.translations.error.required') : $row, $rows);
            }
        }

        return $rows;
    }

    private function title(AdminResource $resource, TranslatableInterface $entity): string
    {
        $title = $this->accessor->getValue($entity->translate('fr'), $this->dashboard->titlePath($resource));

        return \is_string($title) ? $title : '';
    }
}
