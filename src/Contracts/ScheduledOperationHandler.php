<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use Larena\Queue\Data\DispatchResult;
use Larena\Queue\Data\QueueJobSnapshot;
use Larena\Scheduler\Data\ScheduledDispatchContext;

interface ScheduledOperationHandler
{
    public function operationRef(): string;

    public function dispatch(ScheduledDispatchContext $context): DispatchResult;

    public function status(string $jobId): QueueJobSnapshot;
}
