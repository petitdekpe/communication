<?php

declare(strict_types=1);

namespace App\Retention;

use App\Entity\ConsultationContribution;
use App\Entity\NewsletterSubscriber;
use App\Entity\ServiceRequest;
use App\Enum\RequestStatus;
use App\Enum\SubscriptionStatus;
use App\ServiceRequest\AttachmentStorage;
use App\Settings\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Purge des données personnelles arrivées au terme de leur durée de conservation (conformité APDP).
 *
 * Les durées (en mois) se règlent dans les paramètres du site ; une durée non renseignée désactive la purge de la
 * catégorie. Règles :
 *  - demandes : seulement celles qui sont traitées (acceptées ou refusées), mois comptés depuis la dernière modification ;
 *    une demande reçue ou en cours d'examen n'est jamais purgée automatiquement ;
 *  - contributions aux consultations : mois comptés depuis la réception ;
 *  - inscrits à la lettre : désinscrits (depuis la désinscription) et inscriptions jamais confirmées (depuis la demande) ;
 *    un abonné confirmé est conservé tant qu'il ne se désinscrit pas.
 * Les pièces jointes sont effacées du stockage privé avant la ligne en base ; si un fichier ne peut pas être effacé, la ligne
 * est conservée (nouvelle tentative au passage suivant) pour ne jamais laisser un fichier orphelin.
 */
final class PersonalDataPurger
{
    private const int BATCH = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteSettings $settings,
        private readonly AttachmentStorage $storage,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function purge(bool $dryRun = false): PurgeReport
    {
        return new PurgeReport(
            $this->purgeRequests($dryRun),
            $this->purgeContributions($dryRun),
            $this->purgeSubscribers($dryRun),
        );
    }

    private function purgeRequests(bool $dryRun): int
    {
        $cutoff = $this->cutoff(SiteSettings::RETENTION_REQUESTS);
        if (null === $cutoff) {
            return 0;
        }

        $count = 0;
        do {
            /** @var list<ServiceRequest> $batch */
            $batch = $this->entityManager->createQueryBuilder()
                ->select('r')->from(ServiceRequest::class, 'r')
                ->where('r.status IN (:done)')->andWhere('r.updatedAt < :cutoff')
                ->setParameter('done', [RequestStatus::Approved, RequestStatus::Rejected])
                ->setParameter('cutoff', $cutoff)
                ->setMaxResults(self::BATCH)
                ->getQuery()->getResult();

            $removed = 0;
            foreach ($batch as $request) {
                if ($dryRun) {
                    ++$count;
                    continue;
                }
                if ($this->deleteFiles(array_map(static fn ($a): string => $a->getPath(), $request->getAttachments()->toArray()), $request->getTrackingNumber())) {
                    $this->entityManager->remove($request);
                    ++$removed;
                }
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            $count += $removed;
        } while (!$dryRun && self::BATCH === \count($batch) && $removed > 0);

        return $count;
    }

    private function purgeContributions(bool $dryRun): int
    {
        $cutoff = $this->cutoff(SiteSettings::RETENTION_CONTRIBUTIONS);
        if (null === $cutoff) {
            return 0;
        }

        $count = 0;
        do {
            /** @var list<ConsultationContribution> $batch */
            $batch = $this->entityManager->createQueryBuilder()
                ->select('c')->from(ConsultationContribution::class, 'c')
                ->where('c.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->setMaxResults(self::BATCH)
                ->getQuery()->getResult();

            $removed = 0;
            foreach ($batch as $contribution) {
                if ($dryRun) {
                    ++$count;
                    continue;
                }
                $path = $contribution->getFilePath();
                if ($this->deleteFiles(null === $path ? [] : [$path], 'contribution '.$contribution->getId())) {
                    $this->entityManager->remove($contribution);
                    ++$removed;
                }
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            $count += $removed;
        } while (!$dryRun && self::BATCH === \count($batch) && $removed > 0);

        return $count;
    }

    private function purgeSubscribers(bool $dryRun): int
    {
        $cutoff = $this->cutoff(SiteSettings::RETENTION_SUBSCRIBERS);
        if (null === $cutoff) {
            return 0;
        }

        $query = $this->entityManager->createQueryBuilder()
            ->select('s')->from(NewsletterSubscriber::class, 's')
            ->where('(s.status = :unsubscribed AND s.unsubscribedAt < :cutoff) OR (s.status = :pending AND s.subscribedAt < :cutoff)')
            ->setParameter('unsubscribed', SubscriptionStatus::Unsubscribed)
            ->setParameter('pending', SubscriptionStatus::Pending)
            ->setParameter('cutoff', $cutoff);

        if ($dryRun) {
            /** @var list<NewsletterSubscriber> $found */
            $found = $query->getQuery()->getResult();

            return \count($found);
        }

        $count = 0;
        /** @var iterable<NewsletterSubscriber> $subscribers */
        $subscribers = $query->getQuery()->toIterable();
        foreach ($subscribers as $subscriber) {
            $this->entityManager->remove($subscriber);
            ++$count;
        }
        $this->entityManager->flush();

        return $count;
    }

    private function cutoff(string $setting): ?\DateTimeImmutable
    {
        $months = $this->settings->getPositiveInt($setting);

        return null === $months ? null : $this->clock->now()->modify(\sprintf('-%d months', $months));
    }

    /**
     * @param list<string> $paths
     */
    private function deleteFiles(array $paths, string $reference): bool
    {
        try {
            foreach ($paths as $path) {
                $this->storage->deletePath($path);
            }
        } catch (FilesystemException $exception) {
            $this->logger->error('Purge : fichier non effacé pour {reference}, ligne conservée.', ['reference' => $reference, 'exception' => $exception]);

            return false;
        }

        return true;
    }
}
