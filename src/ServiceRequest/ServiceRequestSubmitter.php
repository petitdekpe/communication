<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Entity\Service;
use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Enregistre une demande validée : numéro de suivi, pièces jointes (stockage privé), base de données,
 * puis accusé de réception. Si l'enregistrement échoue, les fichiers déjà écrits sont supprimés.
 */
final class ServiceRequestSubmitter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackingNumberGenerator $trackingNumbers,
        private readonly AttachmentStorage $attachments,
        private readonly AcknowledgmentMailer $mailer,
        private readonly ClockInterface $clock,
    ) {
    }

    public function submit(Service $service, RequestData $data, string $locale): ServiceRequest
    {
        $request = new ServiceRequest(
            $this->trackingNumbers->generate(),
            $service,
            $data->getEmail(),
            $locale,
            $data->toPayload(),
            $this->clock->now(),
        );

        /** @var list<ServiceRequestAttachment> $stored */
        $stored = [];
        try {
            foreach ($data->getAttachments() as $file) {
                $stored[] = $this->attachments->store($request, $file);
            }
            $this->entityManager->persist($request);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            foreach ($stored as $attachment) {
                $this->attachments->delete($attachment);
            }

            throw $e;
        }

        // Après l'enregistrement : un échec d'envoi ne fait jamais perdre la demande.
        $this->mailer->sendAcknowledgment($request);

        return $request;
    }
}
