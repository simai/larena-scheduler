<?php

declare(strict_types=1);

namespace Larena\Scheduler\Data;

use DateTimeImmutable;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;
use Larena\Scheduler\Enums\ScheduleState;

final readonly class ScheduleSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public string $operationRef,
        public string $actorRef,
        public string $timezone,
        public ScheduleFrequency $frequency,
        public ?string $localTime,
        public ?int $weekday,
        public MissedRunStrategy $missedRunStrategy,
        public int $retentionCount,
        public ScheduleState $state,
        public DateTimeImmutable $nextRunAt,
        public ?DateTimeImmutable $lastDueAt,
        public ?DateTimeImmutable $lastDispatchedAt,
        public ?string $lastJobId,
        public ?string $lastJobStatus,
        public ?int $lastResultSizeBytes,
        public ?string $lastFailureReason,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
