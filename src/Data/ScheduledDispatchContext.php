<?php

declare(strict_types=1);

namespace Larena\Scheduler\Data;

use DateTimeImmutable;

final readonly class ScheduledDispatchContext
{
    public function __construct(
        public ScheduleSnapshot $schedule,
        public DateTimeImmutable $dueAt,
        public string $idempotencyKey,
        public string $correlationId,
    ) {
    }
}
