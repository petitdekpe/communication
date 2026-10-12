<?php

declare(strict_types=1);

namespace App\Retention;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgePersonalDataHandler
{
    public function __construct(
        private readonly PersonalDataPurger $purger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgePersonalData $message): void
    {
        $report = $this->purger->purge();

        // Seuls des nombres sont journalisés : aucune donnée personnelle.
        $this->logger->info('Purge APDP : {requests} demande(s), {contributions} contribution(s), {subscribers} inscrit(s) supprimés.', [
            'requests' => $report->requests,
            'contributions' => $report->contributions,
            'subscribers' => $report->subscribers,
        ]);
    }
}
