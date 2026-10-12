<?php

declare(strict_types=1);

namespace App\Consultation;

use App\Entity\Consultation;
use App\Entity\ConsultationContribution;
use App\ServiceRequest\AttachmentStorage;
use App\ServiceRequest\StoredFile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Enregistre une contribution : fichier dans le stockage privé (hors racine web), puis base de données.
 * Si l'enregistrement échoue, le fichier déjà écrit est supprimé.
 */
final class ContributionSubmitter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttachmentStorage $storage,
        private readonly ClockInterface $clock,
    ) {
    }

    public function submit(Consultation $consultation, ContributionData $data): ConsultationContribution
    {
        $organization = trim((string) $data->organization);
        $contribution = new ConsultationContribution(
            $consultation,
            trim((string) $data->name),
            '' === $organization ? null : $organization,
            trim((string) $data->text),
            $this->clock->now(),
        );

        $stored = null;
        try {
            if (null !== $data->file) {
                $stored = $this->storage->put($data->file);
                $contribution->attachFile($stored->path, $stored->originalName, $stored->mimeType, $stored->size);
            }
            $this->entityManager->persist($contribution);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if ($stored instanceof StoredFile) {
                $this->storage->deletePath($stored->path);
            }

            throw $e;
        }

        return $contribution;
    }
}
