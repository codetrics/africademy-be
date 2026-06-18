<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::cron(
                    '0 3 * * *',
                    new RunCommandMessage('cache:clear --env=prod --no-debug'),
                ),
                RecurringMessage::every(
                    '1 minute',
                    new RunCommandMessage('app:notifications:run'),
                ),
                RecurringMessage::cron(
                    '0 4 * * *',
                    new RunCommandMessage('app:subscriptions:bill'),
                ),
            );
    }
}
