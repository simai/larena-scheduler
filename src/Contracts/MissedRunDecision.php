<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleDecisionStatus;

final readonly class MissedRunDecision
{
    public function __construct(
        public ScheduleDecisionStatus $status,
        public MissedRunStrategy $strategy,
        public string $lastDueAtUtc,
        public string $checkedAtUtc,
        public bool $adminWarningRequired,
    ) {
    }
}
