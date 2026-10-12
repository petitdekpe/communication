<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Entity\ServiceRequest;
use App\Enum\RequestStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traitement d'une demande par le service presse : nouveau statut et commentaire interne enregistrés ensemble,
 * puis e-mail au demandeur si (et seulement si) le statut a changé.
 */
final class RequestStatusUpdater
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusChangeMailer $mailer,
    ) {
    }

    /**
     * @param string|null $note nouveau commentaire interne (vide = l'effacer) ; null = le laisser tel quel
     *
     * @return bool vrai si le statut a changé (et que le demandeur a donc été prévenu)
     */
    public function update(ServiceRequest $request, RequestStatus $status, ?string $note): bool
    {
        $changed = $request->getStatus() !== $status;

        if (null !== $note) {
            $request->setInternalNote($note);
        }
        $request->setStatus($status);
        $this->entityManager->flush();

        if ($changed) {
            $this->mailer->send($request);
        }

        return $changed;
    }
}
