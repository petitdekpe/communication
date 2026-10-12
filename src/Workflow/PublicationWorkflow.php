<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Contract\PublishableInterface;
use App\Entity\PublicationHistory;
use App\Entity\User;
use App\Enum\PublicationStatus;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Point d'entrée unique du circuit de publication (state machine « publication » de Symfony Workflow).
 *   submit    : brouillon -> en relecture
 *   publish   : en relecture -> publié (date future : publication programmée, déclenchée par le Scheduler)
 *   reject    : en relecture -> brouillon (motif obligatoire)
 *   unpublish : publié -> brouillon
 *   archive   : publié -> archivé
 *   restore   : archivé -> brouillon
 * Les droits sont ceux des Voters, appliqués par PublicationGuardListener.
 */
final class PublicationWorkflow
{
    /** Transitions qui exigent un motif écrit. */
    public const array REASON_REQUIRED = ['reject'];

    public function __construct(
        #[Target('publication')]
        private readonly WorkflowInterface $workflow,
        private readonly PublicationGuardListener $guard,
        private readonly Security $security,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Transitions que l'utilisateur courant peut appliquer à ce contenu.
     *
     * @return list<string>
     */
    public function available(PublishableInterface $content, bool $withReasonRequired = true): array
    {
        $names = [];
        foreach ($this->workflow->getEnabledTransitions($content) as $transition) {
            if ($withReasonRequired || !self::requiresReason($transition->getName())) {
                $names[] = $transition->getName();
            }
        }

        return array_values(array_unique($names));
    }

    public function can(string $name, PublishableInterface $content): bool
    {
        return $this->workflow->can($content, $name);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return ['submit', 'publish', 'reject', 'unpublish', 'archive', 'restore'];
    }

    public static function requiresReason(string $name): bool
    {
        return \in_array($name, self::REASON_REQUIRED, true);
    }

    /**
     * @throws \DomainException transition refusée ou motif manquant
     */
    public function apply(string $name, PublishableInterface $content, ?string $reason = null): void
    {
        $reason = null === $reason ? null : trim($reason);
        if ('' === $reason) {
            $reason = null;
        }
        if (self::requiresReason($name) && null === $reason) {
            throw new \DomainException('Un motif est obligatoire pour cette transition.');
        }
        if (!$this->can($name, $content)) {
            throw new \DomainException(\sprintf('Transition « %s » impossible pour ce contenu.', $name));
        }

        $this->doApply($name, $content, $reason);
        $this->entityManager->flush();
    }

    /**
     * Le relecteur fixe une date de publication future : le contenu reste en relecture, le Scheduler le publiera.
     *
     * @throws \DomainException
     */
    public function schedule(PublishableInterface $content, \DateTimeImmutable $at): void
    {
        if (!$this->can('publish', $content)) {
            throw new \DomainException('Programmation impossible pour ce contenu.');
        }
        if ($at <= $this->clock->now()) {
            throw new \DomainException('La date de publication programmée doit être dans le futur.');
        }

        $content->setScheduledAt($at);
        $this->entityManager->persist($this->history($content, 'schedule', $content->getStatus(), $content->getStatus(), $at->format('c')));
        $this->entityManager->flush();
    }

    /**
     * @throws \DomainException
     */
    public function unschedule(PublishableInterface $content): void
    {
        if (null === $content->getScheduledAt() || !$this->security->isGranted(Permission::CONTENT_PUBLISH, $content)) {
            throw new \DomainException('Aucune publication programmée à annuler.');
        }

        $content->setScheduledAt(null);
        $this->entityManager->persist($this->history($content, 'unschedule', $content->getStatus(), $content->getStatus(), null));
        $this->entityManager->flush();
    }

    /**
     * Publication déclenchée par le Scheduler, sans utilisateur connecté. Retourne faux si le contenu n'est plus à publier.
     */
    public function publishDue(PublishableInterface $content): bool
    {
        $due = $content->getScheduledAt();
        if (null === $due || $due > $this->clock->now() || PublicationStatus::InReview !== $content->getStatus()) {
            return false;
        }

        $this->guard->runAsSystem(fn () => $this->doApply('publish', $content, null));
        $this->entityManager->flush();

        return true;
    }

    private function doApply(string $name, PublishableInterface $content, ?string $reason): void
    {
        $from = $content->getStatus();
        $scheduledAt = $content->getScheduledAt();

        $this->workflow->apply($content, $name, ['reason' => $reason]);

        if ('publish' === $name && null !== $scheduledAt) {
            $content->setPublishedAt($scheduledAt);
        }
        $content->setScheduledAt(null);
        $this->entityManager->persist($this->history($content, $name, $from, $content->getStatus(), $reason));
    }

    private function history(PublishableInterface $content, string $transition, PublicationStatus $from, PublicationStatus $to, ?string $reason): PublicationHistory
    {
        $user = $this->security->getUser();
        $id = method_exists($content, 'getId') ? $content->getId() : null;

        return new PublicationHistory(
            $content::class,
            \is_int($id) ? $id : 0,
            $transition,
            $from->value,
            $to->value,
            $user?->getUserIdentifier() ?? 'system',
            $user instanceof User ? $user->getName() : null,
            $reason,
            $this->clock->now(),
        );
    }
}
