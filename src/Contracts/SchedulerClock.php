<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use DateTimeImmutable;

interface SchedulerClock
{
    public function now(): DateTimeImmutable;
}
