<?php

declare(strict_types=1);

namespace App\Version;

use App\Entity\ContentVersion;
use App\Entity\Contract\PublishableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Enregistre une version à chaque enregistrement d'un contenu, lit l'historique et restaure une version.
 */
final class VersionRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentSnapshotter $snapshotter,
        private readonly ClockInterface $clock,
        private readonly Security $security,
    ) {
    }

    /**
     * Crée la version suivante, sauf si le contenu n'a pas changé depuis la dernière (enregistrement sans modification).
     * Le contenu doit déjà avoir un identifiant (flush préalable).
     */
    public function record(PublishableInterface $content, ?int $restoredFrom = null): ?ContentVersion
    {
        $id = $this->idOf($content);
        $snapshot = $this->snapshotter->snapshot($content);
        $latest = $this->latest($content);
        if (null !== $latest && null === $restoredFrom && $latest->getSnapshot() === $snapshot) {
            return null;
        }

        $version = new ContentVersion(
            $content::class,
            $id,
            ($latest?->getNumber() ?? 0) + 1,
            $snapshot,
            $this->security->getUser()?->getUserIdentifier() ?? 'system',
            $this->clock->now(),
            $restoredFrom,
        );
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $version;
    }

    /**
     * @return list<ContentVersion> du plus récent au plus ancien
     */
    public function history(PublishableInterface $content): array
    {
        return $this->entityManager->getRepository(ContentVersion::class)->findBy(
            ['subjectClass' => $content::class, 'subjectId' => $this->idOf($content)],
            ['number' => 'DESC'],
        );
    }

    public function find(PublishableInterface $content, int $number): ?ContentVersion
    {
        return $this->entityManager->getRepository(ContentVersion::class)->findOneBy(
            ['subjectClass' => $content::class, 'subjectId' => $this->idOf($content), 'number' => $number],
        );
    }

    /**
     * Rétablit le contenu de la version, puis enregistre cette restauration comme une nouvelle version.
     */
    public function restore(PublishableInterface $content, ContentVersion $version): ContentVersion
    {
        $this->snapshotter->restore($content, $version->getSnapshot());
        $this->entityManager->flush();

        return $this->record($content, $version->getNumber()) ?? $version;
    }

    private function latest(PublishableInterface $content): ?ContentVersion
    {
        return $this->entityManager->getRepository(ContentVersion::class)->findOneBy(
            ['subjectClass' => $content::class, 'subjectId' => $this->idOf($content)],
            ['number' => 'DESC'],
        );
    }

    private function idOf(PublishableInterface $content): int
    {
        $id = method_exists($content, 'getId') ? $content->getId() : null;

        return \is_int($id) ? $id : throw new \LogicException('Le contenu doit être enregistré avant d\'être versionné.');
    }
}
