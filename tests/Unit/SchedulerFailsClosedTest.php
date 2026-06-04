<?php

declare(strict_types=1);

use Larena\Scheduler\Contracts\LockDecision;
use Larena\Scheduler\Contracts\MissedRunDecision;
use Larena\Scheduler\Contracts\TriggerDecision;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleDecisionStatus;
use Larena\Scheduler\Enums\TickSource;

assert(TickSource::Web->degraded() === true);
assert(TickSource::Cron->degraded() === false);

$webTickDecision = new TriggerDecision(
    status: ScheduleDecisionStatus::Rejected,
    scheduleId: 'dangerous',
    dueAtUtc: '2026-06-04T11:00:00Z',
    projectTimezone: 'UTC',
    tickSource: TickSource::Web,
    reasons: ['web_tick_cannot_select_arbitrary_schedule'],
);

assert($webTickDecision->canCreateWork() === false);

$staleLock = new LockDecision(false, 'scheduler:dangerous', 'idem-2', true, ['stale_lock']);
assert($staleLock->allowsDispatch() === false);

$missed = new MissedRunDecision(
    status: ScheduleDecisionStatus::Missed,
    strategy: MissedRunStrategy::RejectBurst,
    lastDueAtUtc: '2026-06-04T10:00:00Z',
    checkedAtUtc: '2026-06-04T11:00:00Z',
    adminWarningRequired: true,
);

assert($missed->adminWarningRequired === true);
