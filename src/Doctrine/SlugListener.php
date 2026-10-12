<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Contract\SluggableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Slugs des traductions : générés depuis getSlugSource() quand ils sont vides (création, ou slug vidé
 * lors d'une modification), uniques par langue et par type de contenu (suffixe -2, -3… si besoin).
 * Un slug existant n'est jamais modifié automatiquement : les URL publiées restent stables.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class SlugListener
{
    private const int MAX_LENGTH = 180;

    /** @var array<string, true> slugs attribués pendant le flush en cours (pas encore en base) */
    private array $reserved = [];

    public function prePersist(PrePersistEventArgs $args): void
    {
        $translation = $args->getObject();
        if ($translation instanceof SluggableInterface && '' === (string) $translation->getSlug()) {
            $translation->setSlug($this->generate($args->getObjectManager(), $translation));
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $translation = $args->getObject();
        if ($translation instanceof SluggableInterface && $args->hasChangedField('slug') && '' === (string) $args->getNewValue('slug')) {
            $args->setNewValue('slug', $this->generate($args->getObjectManager(), $translation));
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->reserved = [];
    }

    private function generate(EntityManagerInterface $em, SluggableInterface $translation): string
    {
        $base = (new AsciiSlugger($translation->getLocale()))
            ->slug($translation->getSlugSource())
            ->lower()
            ->truncate(self::MAX_LENGTH, cut: false)
            ->trim('-')
            ->toString();
        if ('' === $base) {
            $base = 'contenu';
        }

        $slug = $base;
        for ($suffix = 2; $this->isTaken($em, $translation, $slug); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }
        $this->reserved[$this->key($translation, $slug)] = true;

        return $slug;
    }

    private function isTaken(EntityManagerInterface $em, SluggableInterface $translation, string $slug): bool
    {
        if (isset($this->reserved[$this->key($translation, $slug)])) {
            return true;
        }

        $count = $em->createQueryBuilder()
            ->select('COUNT(t)')
            ->from($translation::class, 't')
            ->where('t.locale = :locale')
            ->andWhere('t.slug = :slug')
            ->setParameter('locale', $translation->getLocale())
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    private function key(SluggableInterface $translation, string $slug): string
    {
        return $translation::class.'|'.$translation->getLocale().'|'.$slug;
    }
}
