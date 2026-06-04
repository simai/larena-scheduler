<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

final readonly class DispatchPlan
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $scheduleId,
        public string $operationRef,
        public string $queueProfileRef,
        public string $correlationId,
        public array $payload = [],
        public bool $heavyWork = true,
    ) {
    }
}
