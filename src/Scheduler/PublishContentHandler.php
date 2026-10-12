<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Contract\PublishableInterface;
use App\Workflow\PublicationWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Publie le contenu (transition « publish », sans utilisateur). Idempotent : si le contenu a été modifié, refusé
 * ou déprogrammé entre-temps, il n'est pas publié.
 */
#[AsMessageHandler]
final class PublishContentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationWorkflow $workflow,
    ) {
    }

    public function __invoke(PublishContent $message): void
    {
        $content = $this->entityManager->find($message->entityClass, $message->id);
        if ($content instanceof PublishableInterface) {
            $this->workflow->publishDue($content);
        }
    }
}
