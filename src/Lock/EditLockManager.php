<?php

declare(strict_types=1);

namespace App\Lock;

use App\Entity\Contract\PublishableInterface;
use App\Entity\EditLock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Verrou d'édition : le premier qui ouvre un contenu le détient ; un autre utilisateur est averti (avec le nom du
 * détenteur) mais n'est pas empêché de travailler. Le verrou expire après 15 minutes sans activité (la page
 * envoie un signal tant que l'utilisateur écrit).
 */
final class EditLockManager
{
    public const int TIMEOUT_MINUTES = 15;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Prend le verrou s'il est libre, expiré ou déjà à l'utilisateur (dans ce dernier cas, le prolonge).
     * Retourne le détenteur actuel : null si c'est l'utilisateur lui-même.
     *
     * @return array{name: string, since: \DateTimeImmutable, expiresAt: \DateTimeImmutable}|null
     */
    public function acquire(PublishableInterface $content, UserInterface $user): ?array
    {
        $now = $this->clock->now();
        $subjectId = $this->idOf($content);
        $lock = $this->find($content, $subjectId);

        if (null === $lock) {
            $this->entityManager->persist(new EditLock($content::class, $subjectId, $user->getUserIdentifier(), $this->nameOf($user), $now));
            $this->entityManager->flush();

            return null;
        }

        if ($lock->getHolder() === $user->getUserIdentifier()) {
            $lock->touch($now);
            $this->entityManager->flush();

            return null;
        }

        if ($this->isExpired($lock, $now)) {
            $lock->take($user->getUserIdentifier(), $this->nameOf($user), $now);
            $this->entityManager->flush();

            return null;
        }

        return ['name' => $lock->getHolderName(), 'since' => $lock->getLastActivityAt(), 'expiresAt' => $lock->getLastActivityAt()->modify('+'.self::TIMEOUT_MINUTES.' minutes')];
    }

    /** Libère le verrou s'il appartient à l'utilisateur. */
    public function release(PublishableInterface $content, UserInterface $user): void
    {
        $lock = $this->find($content, $this->idOf($content));
        if (null !== $lock && $lock->getHolder() === $user->getUserIdentifier()) {
            $this->entityManager->remove($lock);
            $this->entityManager->flush();
        }
    }

    private function find(PublishableInterface $content, int $subjectId): ?EditLock
    {
        return $this->entityManager->getRepository(EditLock::class)->findOneBy(['subjectClass' => $content::class, 'subjectId' => $subjectId]);
    }

    private function isExpired(EditLock $lock, \DateTimeImmutable $now): bool
    {
        return $lock->getLastActivityAt()->modify('+'.self::TIMEOUT_MINUTES.' minutes') <= $now;
    }

    private function nameOf(UserInterface $user): string
    {
        return $user instanceof User && null !== $user->getName() && '' !== $user->getName() ? $user->getName() : $user->getUserIdentifier();
    }

    private function idOf(PublishableInterface $content): int
    {
        $id = method_exists($content, 'getId') ? $content->getId() : null;

        return \is_int($id) ? $id : throw new \LogicException('Le contenu doit être enregistré avant d\'être verrouillé.');
    }
}
