<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use Larena\Scheduler\Enums\ScheduleDecisionStatus;
use Larena\Scheduler\Enums\TickSource;

final readonly class TriggerDecision
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public ScheduleDecisionStatus $status,
        public string $scheduleId,
        public string $dueAtUtc,
        public string $projectTimezone,
        public TickSource $tickSource,
        public array $reasons = [],
    ) {
    }

    public function canCreateWork(): bool
    {
        return $this->status === ScheduleDecisionStatus::Due;
    }
}
