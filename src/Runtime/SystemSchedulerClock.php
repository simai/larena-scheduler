<?php

declare(strict_types=1);

namespace Larena\Scheduler\Runtime;

use DateTimeImmutable;
use DateTimeZone;
use Larena\Scheduler\Contracts\SchedulerClock;

final readonly class SystemSchedulerClock implements SchedulerClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
