<?php

declare(strict_types=1);

namespace App\Translation;

use App\Admin\AdminResource;
use App\Entity\Contract\TranslatableInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Export XLIFF 1.2 (français vers anglais) pour un traducteur ou un outil de gestion de traduction. Un élément <file>
 * par contenu (original = « ressource/identifiant », ex. « articles/12 »), une unité de traduction par champ texte
 * renseigné en français. La cible reprend l'anglais existant, ou reste vide.
 */
final class XliffExporter
{
    public const string NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';

    private readonly PropertyAccessorInterface $accessor;

    public function __construct(private readonly TranslationFields $fields)
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param list<array{resource: AdminResource, id: int, entity: TranslatableInterface}> $contents
     */
    public function export(array $contents): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NAMESPACE, 'xliff');
        $root->setAttribute('version', '1.2');
        $document->appendChild($root);

        foreach ($contents as $content) {
            $french = $content['entity']->getTranslation('fr');
            if (null === $french) {
                continue;
            }
            $english = $content['entity']->getTranslation('en');

            $file = $document->createElementNS(self::NAMESPACE, 'file');
            $file->setAttribute('original', $content['resource']->id.'/'.$content['id']);
            $file->setAttribute('source-language', 'fr');
            $file->setAttribute('target-language', 'en');
            $file->setAttribute('datatype', 'plaintext');
            $body = $document->createElementNS(self::NAMESPACE, 'body');
            $file->appendChild($body);

            foreach ($this->fields->for($content['resource']) as $field) {
                $source = $this->accessor->getValue($french, $field);
                if (!\is_string($source) || '' === trim($source)) {
                    continue;
                }
                $target = null === $english ? null : $this->accessor->getValue($english, $field);

                $unit = $document->createElementNS(self::NAMESPACE, 'trans-unit');
                $unit->setAttribute('id', $field);
                $unit->setAttribute('resname', $field);
                $unit->appendChild($document->createElementNS(self::NAMESPACE, 'source', ''))->appendChild($document->createTextNode($source));
                $targetNode = $document->createElementNS(self::NAMESPACE, 'target');
                if (\is_string($target) && '' !== trim($target)) {
                    $targetNode->appendChild($document->createTextNode($target));
                } else {
                    $targetNode->setAttribute('state', 'new');
                }
                $unit->appendChild($targetNode);
                $body->appendChild($unit);
            }

            if ($body->hasChildNodes()) {
                $root->appendChild($file);
            }
        }

        return (string) $document->saveXML();
    }
}
