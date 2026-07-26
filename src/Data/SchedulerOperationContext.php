<?php

declare(strict_types=1);

namespace Larena\Scheduler\Data;

final readonly class SchedulerOperationContext
{
    public function __construct(public string $actorRef, public string $correlationRef)
    {
        if (
            preg_match('/\A[a-z][a-z0-9_-]*:[A-Za-z0-9][A-Za-z0-9:._-]{0,191}\z/D', $actorRef) !== 1
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._-]{0,127}\z/D', $correlationRef) !== 1
        ) {
            throw new \InvalidArgumentException('scheduler_operation_context_invalid');
        }
    }
}
