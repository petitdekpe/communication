<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Contract\PublishableInterface;
use App\Security\Permission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Protège chaque transition par un Voter (ContentVoter) : « soumettre » demande CONTENT_SUBMIT,
 * toutes les autres CONTENT_PUBLISH. Le Scheduler, sans utilisateur, passe par runAsSystem().
 */
final class PublicationGuardListener
{
    private bool $system = false;

    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runAsSystem(callable $callback): mixed
    {
        $this->system = true;
        try {
            return $callback();
        } finally {
            $this->system = false;
        }
    }

    #[AsEventListener(event: 'workflow.publication.guard')]
    public function onGuard(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        if ($this->system || !$subject instanceof PublishableInterface) {
            return;
        }

        $permission = 'submit' === $event->getTransition()->getName() ? Permission::CONTENT_SUBMIT : Permission::CONTENT_PUBLISH;
        if (!$this->security->isGranted($permission, $subject)) {
            $event->setBlocked(true, 'Droit insuffisant pour cette transition.');
        }
    }
}
