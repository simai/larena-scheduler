<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use Larena\Scheduler\Enums\ScheduleDecisionStatus;

final readonly class ScheduleRunResult
{
    /**
     * @param list<string> $messages
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        public ScheduleDecisionStatus $status,
        public string $scheduleId,
        public array $messages = [],
        public array $diagnostics = [],
        public ?string $auditEventRef = null,
    ) {
    }
}
