<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Retention\PurgePersonalData;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Planning de l'application. Consommé par : php bin/console messenger:consume scheduler_default async.
 */
#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache) // les échéances manquées pendant un arrêt du worker sont rattrapées
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('1 minute', new CheckScheduledPublications()),
                // Purge APDP : chaque nuit à 03 h 30 (heure du serveur).
                RecurringMessage::cron('30 3 * * *', new PurgePersonalData()),
            );
    }
}
